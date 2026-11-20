<?php

namespace BasicHub\EsCore\Consumer;

use EasySwoole\Spl\SplBean;

/**
 * 自定义消费进程配置
 * 1. 处理超高并发：可设置多个进程监听一个队列，且同队列可设置多分片，避免集群单片内存上限。
 * 2. 处理超低并发，节省进程资源开销： 可将多队列任务通过children属性放置在同一个进程中处理，节省资源。
 * 3. 合并Class逻辑： 可多任务共用Class，通过Config自定分发
 */
class Config extends SplBean
{
    /**
     * 进程名（简短标识符）
     * @var string
     */
    protected $proName = '';

    /**
     * 运行类名
     * @var string
     */
    protected $className = '';

    /**
     * 进程数量
     * @var int
     */
    protected $proNum = 1;

    /**
     * 运行在哪些服务器上，服务器编号数组
     * @var array
     */
    protected $serverNumber = [];

    /**
     * 覆盖运行的进程的启动参数，参数值见：\EasySwoole\Component\Process\Config
     * @var array
     */
    protected $swProConfig = [];

    /********************* 以上为进程级参数，在进程启动前就会使用 **********************/

    /**
     * 多久运行一次，单位毫秒
     * @var int
     */
    protected $tick = 1000;

    /**
     * 单次出队列的数量
     * @var int
     */
    protected $limit = 1000;

    /**
     * 监听队列名称
     * @var string
     */
    protected $queueName = '';

    /**
     * redis连接池名
     *  如有多个连接池，请通过children属性实现
     * @var string
     */
    protected $poolName = 'default';

    /**
     * 非集群模式下不要开此参数，无意义。
     * 在集群模式中，将队列数据均匀分布在不同分片的槽位中。
     *      分key数从1开始，例如配置3，则监听 name、name.1、name.2、name.3
     *      普通模式切换为分片模式：直接生效，因为默认也会监听name队列。
     *      分片模式切换为普通模式：切换后，只会监听name队列，对应的name.n队列里如果还有数据，需要运行 RPOPLPUSH 命令，将name.n队列数据移入name队列。
     * 见函数：redis_list_push
     * @var int
     */
    protected $clusterShardNumber = 0;

    /**
     * 取出数据后，是否需要json_decode
     * @var bool
     */
    protected $toJson = false;

    /**
     * 最大并发消费协程数，仅长任务模式（BaseLongTrait）生效
     *  长任务模式下消费跑在独立协程中，定时器回调不再被消费耗时拖住，失去了天然的串行节流。
     *  队列积压时每轮tick都会继续出队，协程会无上限堆积，各自占用一条池连接，打满连接池后
     *  getObj 等待超时返回 null，而数据已出队无法退回。故以「在跑协程数」反向约束每轮出队量，
     *  满载时本轮不出队，数据留在redis中（比堆在进程内存中安全，进程重启不丢）。
     *  取值建议不超过连接池 maxObjectNum 的一半，为业务层其他取连接操作留出余量。
     * @var int
     */
    protected $maxConcurrency = 10;

    /**
     * 允许自嵌套一层，表示需要在一个进程内开启多个任务监听不同队列，只需要非进程级参数
     * @var array
     */
    protected $children = [];

    public function getProName()
    {
        return $this->proName;
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getProNum()
    {
        return $this->proNum;
    }

    public function getTick()
    {
        return $this->tick;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function getQueueName()
    {
        return $this->queueName;
    }

    public function getPoolName()
    {
        return $this->poolName;
    }

    public function getServerNumber()
    {
        return $this->serverNumber;
    }

    public function getClusterShardNumber()
    {
        return $this->clusterShardNumber;
    }

    public function getToJson()
    {
        return $this->toJson;
    }

    public function getMaxConcurrency()
    {
        return $this->maxConcurrency;
    }

    public function getSwProConfig()
    {
        return $this->swProConfig;
    }

    public function getChildren()
    {
        return $this->children;
    }
}
