<?php

namespace BasicHub\EsCore\Common\Notify\Webhook;

use BasicHub\EsCore\Common\Notify\NotifyInterface;
use EasySwoole\Spl\SplBean;

/**
 * webhook消息推送
 */
abstract class Base extends SplBean implements NotifyInterface
{
    /**
     * webhook url
     * @var string
     */
    protected $url = '';

    /**
     * webhook 密钥
     * @var string
     */
    protected $signKey = '';

    /**
     * 是否需要验签
     * @var string
     */
    protected $openSign = true;

    protected $isdebug = false;

}
