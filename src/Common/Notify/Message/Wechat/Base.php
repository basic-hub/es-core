<?php

namespace BasicHub\EsCore\Common\Notify\Message\Wechat;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;
use EasySwoole\Spl\SplBean;

/**
 * 每个微信模板结构都不同，此Message目录仅定义通用的几个，如果各项目需要增加模板，请继承此类
 */
abstract class Base extends SplBean implements MessageInterface
{

}
