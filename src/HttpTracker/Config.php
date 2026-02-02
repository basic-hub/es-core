<?php

namespace BasicHub\EsCore\HttpTracker;

use EasySwoole\Spl\SplBean;

class Config extends SplBean
{
    /**
     * 复发标识
     */
    const REPEATED = ';HttpTracker';
    /**
     * Save处理类, 默认SaveHandler
     * @var string
     */
    protected $saveHandlerClassName = null;

    /**
     * 是否自动保存，受限于框架层实现，此属性无法动态设置
     * @var bool
     */
    protected $saveAuto = true;

    /**
     * save公共参数
     * @var array
     */
    protected $saveGlobalArg = [];

    protected $saveRedisName = 'log';

    protected $saveQueueName = 'Report:Origin-HttpTracker';

    protected $clusterShardNumber = 0;

    public function getSaveHandlerClassName()
    {
        return $this->saveHandlerClassName;
    }

    public function getSaveAuto()
    {
        return $this->saveAuto;
    }

    public function getSaveGlobalArg()
    {
        return $this->saveGlobalArg;
    }

    public function getSaveRedisName()
    {
        return $this->saveRedisName;
    }

    public function getSaveQueueName()
    {
        return $this->saveQueueName;
    }

    public function getClusterShardNumber()
    {
        return $this->clusterShardNumber;
    }
}
