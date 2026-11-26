<?php

namespace BasicHub\EsCore\Common\Notify\App;

use BasicHub\EsCore\Common\Notify\NotifyInterface;
use EasySwoole\Spl\SplBean;

/**
 * 自建应用消息推送
 */
abstract class Base extends SplBean implements NotifyInterface
{
    /**
     * 应用获取tenant_access_token时，需要redis缓存
     * @var string
     */
    protected $redisPoolName = 'admin';

    protected $isdebug = false;

    /**
     * 获取应用access_token
     * @return mixed
     */
    abstract public function getAccessToken();

    /**
     * 上传文件得到media_id
     * @param string $filePath 本地文件绝对路径
     * @param string $type
     * @return mixed
     */
    abstract public function uploadMedia($filePath, $type = '');
}
