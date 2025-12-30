<?php

declare(strict_types=1);

namespace Hyperf\Snowflake\MetaGenerator;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\Locker;
use Hyperf\Redis\RedisProxy;
use Hyperf\Snowflake\ConfigurationInterface;
use Hyperf\Snowflake\MetaGenerator;
use RuntimeException;
use Throwable;

use function Hyperf\Support\make;

abstract class RedisMetaGenerator extends MetaGenerator
{
    public const DEFAULT_REDIS_KEY = 'hyperf:snowflake:workerId';

    public const REDIS_EXPIRE = 60 * 60;

    protected ?int $workerId = null;

    protected ?int $dataCenterId = null;

    public function __construct(ConfigurationInterface $configuration, int $beginTimestamp, protected ConfigInterface $config)
    {
        parent::__construct($configuration, $beginTimestamp);
    }

    public function init()
    {
        if (is_null($this->workerId) || is_null($this->dataCenterId)) {
            if (Locker::lock(static::class)) {
                try {
                    $this->initDataCenterIdAndWorkerId();
                } finally {
                    Locker::unlock(static::class);
                }
            }
        }
    }

    public function getDataCenterId(): int
    {
        $this->init();

        return $this->dataCenterId;
    }

    public function getWorkerId(): int
    {
        $this->init();

        return $this->workerId;
    }

    private function initDataCenterIdAndWorkerId(): void
    {
        if (is_null($this->workerId) || is_null($this->dataCenterId)) {
            $pool = $this->config->get(sprintf('snowflake.%s.pool', static::class), 'default');
            $key = $this->config->get(sprintf('snowflake.%s.key', static::class), static::DEFAULT_REDIS_KEY);

            $this->setDataCenterIdAndWorkerId($key, $pool);
        }
    }

    private function setDataCenterIdAndWorkerId(string $key, string $pool, int $depth = 0): void
    {
        $redis = $this->getRedis($pool);

        $id = $redis->incr($key);

        $workerId = $id % $this->configuration->maxWorkerId();
        $dataCenterId = intval($id / $this->configuration->maxWorkerId()) % $this->configuration->maxDataCenterId();

        $workerIdDataCenterIdKey = sprintf('%s:%d_%d', $key, $workerId, $dataCenterId);
        $value = [
            'appName' => $this->config->get('app_name'),
            'createdAt' => date('Y-m-d H:i:s'),
        ];
        $result = $redis->set($workerIdDataCenterIdKey, json_encode($value), ['NX', 'PX' => static::REDIS_EXPIRE * 1000]);
        if ($result === false) {
            if ($depth > 1024) {
                throw new RuntimeException('The value of workerId dataCenterId obtained exceeds 1024, please check your redis data');
            }
            $this->setDataCenterIdAndWorkerId($key, $pool, $depth + 1);
        } else {
            $this->workerId = $workerId;
            $this->dataCenterId = $dataCenterId;
            $this->heartbeat($workerIdDataCenterIdKey, $pool, $value);
        }
    }

    private function heartbeat(string $workerIdDataCenterIdKey, string $pool, array $value): void
    {
        if (! Coroutine::inCoroutine()) {
            return;
        }

        Coroutine::create(function () use ($workerIdDataCenterIdKey, $pool, $value) {
            while (true) {
                if (CoordinatorManager::until(Constants::WORKER_EXIT)->yield(5 * 60)) {
                    $redis = $this->getRedis($pool);
                    $redis->del($workerIdDataCenterIdKey);
                    break;
                }
                try {
                    $redis = $this->getRedis($pool);
                    $redis->set($workerIdDataCenterIdKey, json_encode($value), ['PX' => static::REDIS_EXPIRE * 1000]);
                } catch (Throwable $throwable) {
                    ApplicationContext::getContainer()?->get(StdoutLoggerInterface::class)?->error($throwable);
                }
            }
        });
    }

    private function getRedis(string $pool): RedisProxy
    {
        return make(
            RedisProxy::class,
            [
                'pool' => $pool,
            ]
        );
    }
}
