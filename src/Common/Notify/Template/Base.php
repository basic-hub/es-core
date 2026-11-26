<?php

namespace BasicHub\EsCore\Common\Notify\Template;

use BasicHub\EsCore\Common\Notify\NotifyInterface;
use EasySwoole\Spl\SplBean;

/**
 * 模板消息推送
 */
abstract class Base extends SplBean implements NotifyInterface
{
    protected $isdebug = false;
}
