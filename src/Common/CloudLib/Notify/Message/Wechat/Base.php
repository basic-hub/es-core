<?php

namespace BasicHub\EsCore\Common\CloudLib\Notify\Message\Wechat;

use EasySwoole\Spl\SplBean;
use BasicHub\EsCore\Common\CloudLib\Notify\MessageInterface;

/**
 * 每个微信模板结构都不同，此Message目录仅定义通用的几个，如果各项目需要增加模板，请继承此类
 */
abstract class Base extends SplBean implements MessageInterface
{

}
