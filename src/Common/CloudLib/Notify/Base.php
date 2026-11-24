<?php

namespace BasicHub\EsCore\Common\CloudLib\Notify;

use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements NotifyInterface
{
    /**
     * 是否开发环境模式,为true则仅记录&输出,不会真实发送
     * @var bool
     */
    protected $isdebug = false;

}
