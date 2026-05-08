<?php

namespace BasicHub\EsCore\Notify\Feishu;

use BasicHub\EsCore\Notify\Interfaces\ConfigInterface;
use BasicHub\EsCore\Notify\Interfaces\NotifyInterface;
use EasySwoole\Spl\SplBean;

class Config extends SplBean implements ConfigInterface
{
    /**
     * WebHook
     * @var string
     */
    protected $url = '';

    /**
     * 密钥
     * @var string
     */
    protected $signKey = '';

    /**
     * 要@哪些人（Open ID 或 User ID）, true-所有人,false-谁也不鸟
     * @var array|bool
     */
    protected $at = false;

    /**
     * 自建应用appId
     * @var string
     */
    protected $appId = '';

    /**
     * 自建应用密钥
     * @var string
     */
    protected $appSecret = '';

    /**
     * 自建应用获取tenant_access_token时，需要redis缓存
     * @var string
     */
    protected $redisPoolName = 'admin';

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setSignKey($signKey)
    {
        $this->signKey = $signKey;
    }

    public function getSignKey()
    {
        return $this->signKey;
    }

    public function setAt($at)
    {
        $this->at = $at;
    }

    public function getAt()
    {
        return $this->at;
    }

    public function setAppId($appId)
    {
        $this->appId = $appId;
    }

    public function getAppId()
    {
        return $this->appId;
    }

    public function setAppSecret($appSecret)
    {
        $this->appSecret = $appSecret;
    }

    public function getAppSecret()
    {
        return $this->appSecret;

    }

    public function setRedisPoolName(string $pool)
    {
        $this->redisPoolName = $pool;
    }

    public function getRedisPoolName()
    {
        return $this->redisPoolName;
    }

    public function getNotifyClass(): NotifyInterface
    {
        return new Notify($this);
    }
}
