<?php

namespace BasicHub\EsCore\HttpTracker;

use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Tracker\Point;
use EasySwoole\Tracker\SaveHandlerInterface;

class SaveHandler implements SaveHandlerInterface
{
    /**
     * @var Config|null
     */
    protected $config = null;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param Point|null $point
     * @param array|null $globalArg
     * @return bool
     */
    function save(?Point $point, ?array $globalArg = []): bool
    {
        if ($array = Point::toArray($point)) {
            try {
                RedisPool::invoke(function (Redis $redis) use ($array, $globalArg) {
                    foreach ($array as $value) {
                        redis_list_push($redis, $this->config->getSaveQueueName(), array_merge($value, $globalArg ?? []), true);
                    }
                }, $this->config->getSaveRedisName());
            } catch (\Exception|\Throwable $e) {
                trace($e->getMessage(), 'error');
                return false;
            }
        }
        return true;
    }
}
