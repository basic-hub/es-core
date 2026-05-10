<?php

namespace BasicHub\EsCore\Notify\Feishu;

use BasicHub\EsCore\Notify\Interfaces\ConfigInterface;
use BasicHub\EsCore\Notify\Interfaces\NotifyInterface;
use EasySwoole\Spl\SplBean;

/**
 * 飞书发送Webhook机器人有两种方式，二选一即可
 * 1. 自定义webhook机器人 （url+signKey）
 * 2. 自建应用推送消息 (receive_id)  https://open.feishu.cn/document/server-docs/im-v1/message/create?appId=cli_a6f0289db033500b
 * 自定义机器人经常受飞书限流限制，导致发送失败，飞书那边给的建议是使用自建应用来推送消息
 */
class Config extends SplBean implements ConfigInterface
{
    /**
     * WebHook参数，url
     * @var string
     */
    protected $url = '';

    /**
     * webhook参数，密钥
     * @var string
     */
    protected $signKey = '';

    /**
     * 要@哪些人（Open ID 或 User ID）, true-所有人,false-谁也不鸟
     * @var array|bool
     */
    protected $at = false;

    /**
     * 自建应用发送时参数，发送的消息类型，枚举值：open_id|union_id|user_id|email|chat_id
     * @var string
     */
    protected $receiveIdType = 'chat_id';

    /**
     * 自建应用发送时参数，消息接收者ID
     * @var string
     */
    protected $receiveId = '';

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

    public function getReceiveIdType()
    {
        return $this->receiveIdType;
    }

    public function getReceiveId()
    {
        return $this->receiveId;

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
