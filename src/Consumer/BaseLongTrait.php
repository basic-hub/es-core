<?php

namespace BasicHub\EsCore\Consumer;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\Trigger;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

/**
 * 适用于非并发场景、长任务的消费队列。且不支持队列分片（刻意为之）
 * @mixin AbstractProcess;
 */
trait BaseLongTrait
{
    /**
     * 各任务「在跑的消费协程数」计数器，key为任务Config对象hash
     * @var array
     */
    private $runningCount = [];

    protected function onException(\Throwable $throwable, ...$args)
    {
        // consume跑在独立协程中、异常已在协程内自行捕获，这里接不到消费异常，只兜run与出队阶段的异常
        Trigger::getInstance()->throwable($throwable);
    }

    /**
     * 消费单条数据，由子类继承实现
     * 独立协程环境，不提供Redis连接，需要时使用invoke获取连接，使用完成立即自动回收
     * @param string|array $data 每一条队列数据
     * @param Config $config 任务配置（注意区分进程参数与任务参数。且同进程多任务场景下，两种参数获取方式不同）
     * @return mixed
     */
    abstract protected function consume($data, Config $config);

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

        // 多定时器处理
        /** @var Config[] $configs */
        $configs = $Config->getChildren();

        if (empty($configs)) {
            $configs = [$Config];
        }
        foreach ($configs as $config) {
            $this->startTick($config);
        }
    }

    protected function startTick($config)
    {
        if ( ! $config instanceof Config) {
            trace("非法进程参数:" . __METHOD__ . '; config=' . var_export($config, true), 'error');
            Trigger::getInstance()->error("非法进程参数:" . __METHOD__ . '; config=' . var_export($config, true));
            return;
        }

        // 同进程内多任务（children）各自独立计数，互不影响。Config对象与进程同生命周期，hash稳定
        $runKey = spl_object_hash($config);

        // 每个定时器监听一个队列，同队列的不同子队列共用同一定时器:
        // 示例：quque_aa 与 queue_bb 不同定时器;  quque_aa 与 quque_aa.1、quque_aa.2 同定时器
        $this->addTick($config->getTick(), function () use ($config, $runKey) {

            // 背压：以「在跑的消费协程数」反向约束本轮出队量。
            // 满载时本轮不出队，数据留在redis中（比出队后堆在进程内存中安全，进程重启不丢）
            $quota = $config->getMaxConcurrency() - ($this->runningCount[$runKey] ?? 0);
            if ($quota <= 0) {
                return;
            }

            RedisPool::invoke(function (Redis $Redis) use ($config, $runKey, $quota) {
                $queue = $config->getQueueName();
                $limit = min($config->getLimit(), $quota);
                $toJson = $config->getToJson();
                for ($i = 0; $i < $limit; ++$i) {
                    // 左出右进
                    $data = $Redis->lPop($queue);
                    // 队列为空时 lPop 返回 false 或 null，需严格判断，避免误杀值为 "0"、"" 等合法数据
                    if ($data === false || $data === null) {
                        break;
                    }
                    if ($toJson) {
                        $decoded = json_decode($data, true);
                        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                            Trigger::getInstance()->error(
                                "json_decode failed: " . json_last_error_msg() . "; queue=$queue; data=$data; continue"
                            );
                            continue;
                        }
                        $data = $decoded;
                    }
                    // 先自增再创建协程，避免协程尚未被调度时计数滞后导致超发
                    $this->runningCount[$runKey] = ($this->runningCount[$runKey] ?? 0) + 1;
                    go(function () use ($config, $data, $runKey) {
                        try {
                            // 多任务在同一进程时可按Config分发到不同方法处理（业务层处理）
                            $this->consume($data, $config);
                        } catch (\Exception|\Throwable $throwable) {
                            Trigger::getInstance()->throwable($throwable);
                        } finally {
                            --$this->runningCount[$runKey];
                        }
                    });
                }
            }, $config->getPoolName());

        });
    }
}
