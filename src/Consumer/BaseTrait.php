<?php

namespace BasicHub\EsCore\Consumer;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\Trigger;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use BasicHub\EsCore\EventMainServerCreate;

/**
 * @mixin AbstractProcess;
 */
trait BaseTrait
{
    protected function onException(\Throwable $throwable, ...$args)
    {
        // 消费的consume是运行在回调内的，在consume发生的异常基本走不到这里
        Trigger::getInstance()->throwable($throwable);
    }

    /**
     * 消费单条数据，由子类继承实现
     * @param string|array $data 每一条队列数据
     * @param Redis $redis redis连接
     * @param Config $config 任务配置（注意区分进程参数与任务参数。且同进程多任务场景下，两种参数获取方式不同）
     * @return mixed
     */
    abstract protected function consume($data, Redis $redis, Config $config);

    /**
     * EasySwoole自定义进程入口
     * @return void|bool
     */
    public function run($arg)
    {
        $Config = $this->getArg();
        if ( ! $Config instanceof Config) {
            trace("非法进程参数:" . __METHOD__ . '; args=' . var_export($Config, true), 'error');
            return;
        }

        if (config('PROCESS_INFO.isopen')) {
            EventMainServerCreate::listenProcessInfo();
        }

        // 多定时器处理
        /** @var Config $childrens */
        $childrens = $Config->getChildren();

        if (empty($childrens)) {
            $childrens = [$Config];
        }
        foreach ($childrens as $children) {
            $this->startTick($children);
        }
    }

    protected function startTick($config)
    {
        if ( ! $config instanceof Config) {
            trace("非法进程参数:" . __METHOD__ . '; config=' . var_export($config, true), 'error');
            return;
        }

        // 分片处理
        $queues = $this->getListenQueues($config);

        // 每个定时器监听一个队列，同队列的不同子队列共用同一定时器:
        // 示例：quque_aa 与 queue_bb 不同定时器;  quque_aa 与 quque_aa.1、quque_aa.2 同定时器
        $this->addTick($config->getTick(), function () use ($queues, $config) {

            RedisPool::invoke(function (Redis $Redis) use ($queues, $config) {
                $limit = $config->getLimit();
                $toJson = $config->getToJson();
                foreach ($queues as $queue) {
                    for ($i = 0; $i < $limit; ++$i) {
                        // 左出右进
                        $data = $Redis->lPop($queue);
                        if ( ! $data) {
                            break;
                        }
                        try {
                            if ($toJson) {
                                $data = json_decode($data, true);
                            }
                            // 多任务在同一进程时可按Config分发到不同方法处理（业务层处理）
                            $this->consume($data, $Redis, $config);
                        } catch (\Exception|\Throwable $throwable) {
                            Trigger::getInstance()->throwable($throwable);
                        }
                    }
                }
            }, $config->getPoolName());

        });
    }

    protected function getListenQueues(Config $config)
    {
        // 在集群模式中，将队列数据均匀分布在不同分片的槽位中
        $shardNumber = $config->getClusterShardNumber();
        $queue = $config->getQueueName();
        $list[] = $queue;
        if ($shardNumber > 0) {
            // 分key数从1开始，例如配置3，则监听 name、name.1、name.2、name.3
            // 普通模式切换为分片模式：直接生效，因为默认也会监听name队列。
            // 分片模式切换为普通模式：切换后，只会监听name队列，对应的name.n队列里如果还有数据，需要运行 RPOPLPUSH 命令，将name.n队列数据移入name队列。
            for ($i = 1; $i <= $shardNumber; ++$i) {
                $list[] = "$queue.$i";
            }
        }

        return $list;
    }
}
