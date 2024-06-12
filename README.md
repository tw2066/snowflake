# PHP hyperf snowflake


- 对官方`hyperf/snowflake`组件功能增强，增加了`工作机器ID`的维护(redis增加心跳和判重)
- 避免了某些情况下，不同进程的`工作机器ID`相同并产生重复ID

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

