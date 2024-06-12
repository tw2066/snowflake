# PHP hyperf snowflake


- 基于`hyperf/snowflake`组件，增强了`工作机器ID`的维护(心跳检查和判重)
- 避免不同进程`工作机器ID`相同的情况

## 安装

```
composer require tangwei/snowflake
```

## 说明

```
redis的hyperf:snowflake:workerId值是自增

例:不同进程值等于1或962时

根据算法
生成出来的 workerId 和 dataCenterId 相同，都等于1和0

即:有可能产生重复ID
```

