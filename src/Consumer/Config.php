<?php

namespace BasicHub\EsCore\Consumer;

use EasySwoole\Spl\SplBean;

/**
 * 自定义消费进程配置
 */
class Config extends SplBean
{
    /**
     * 每个key的分片数需要存储在config中的前缀
     */
    const CSHARD__PREFIX = '__CSHARD__PREFIX__';

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
     * 多久运行一次，单位毫秒
     * @var int
     */
    protected $tick = 1000;

    /**
     * 单次出队列的数量
     * @var int
     */
    protected $limit = 500;

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
     * 运行在哪些服务器上，服务器编号数组
     * @var array
     */
    protected $serverNumber = [];

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
     * 覆盖运行的进程的启动参数，参数值见：\EasySwoole\Component\Process\Config
     * @var array
     */
    protected $swProConfig = [];

    /**
     * 允许自嵌套一层，表示需要在一个进程内开启多个任务监听不同队列
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

    public function getSwProConfig()
    {
        return $this->swProConfig;
    }

    public function getChildren()
    {
        return $this->children;
    }
}
