<?php

namespace BasicHub\EsCore\HttpTracker;

use EasySwoole\Tracker\Point;

/**
 * http_tracker函数按常规方式返回匿名函数时，外层如遇异常，需要主动try catch，否则返回的匿名函数将不会被调用，同时此条信息会被丢弃，出现漏数据情况。
 * 现改造为类 通过 __invoke 保证函数式调用，通过 __destruct 保证释放时一定会被调用
 */
class End
{
    /**
     * @var Point
     */
    protected $Point;

    /**
     * 确保回调只运行一次，不会被重复覆盖，特别是被__destruct覆盖
     * @var bool
     */
    protected $isInvoked = false;

    public function __construct($point = null)
    {
        $point && $this->Point = $point;
    }

    public function __invoke($data = [], int $httpCode = 200)
    {
        $this->end($data, $httpCode);
    }

    /**
     * 在请求发出后、结束前，合并更新 startArg 中的字段（如实际发送的 headers）
     */
    public function updateStartArg(array $merge): void
    {
        if (!$this->Point || $this->isInvoked) {
            return;
        }
        $startArg = $this->Point->getStartArg() ?: [];
        $this->Point->setStartArg(array_merge($startArg, $merge));
    }

    public function __destruct()
    {
        $this->end();
    }

    protected function end($data = [], int $httpCode = 500)
    {
        if (!$this->Point || $this->isInvoked) {
            return;
        }
        $this->isInvoked = true;
        $this->Point->setEndArg(['httpStatusCode' => $httpCode, 'data' => $data])->end();
    }
}
