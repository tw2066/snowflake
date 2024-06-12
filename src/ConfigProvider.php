<?php

declare(strict_types=1);

namespace Tangwei\Snowflake;


use Hyperf\Snowflake\MetaGenerator\RedisMetaGenerator;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
            ],
            'annotations' => [
                'scan' => [
                    'class_map' => [
                        RedisMetaGenerator::class => __DIR__ . '/../class_map/RedisMetaGenerator.php',
                    ],
                ],
            ],
            'publish' => [
            ],
        ];
    }
}
