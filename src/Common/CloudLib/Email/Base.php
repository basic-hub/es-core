<?php

namespace BasicHub\EsCore\Common\CloudLib\Email;

use EasySwoole\Spl\SplBean;

abstract class Base  extends SplBean implements EmailInterface
{
    /**
     * http_tracker日志的父级id，如果在子协程（名义上的子协程，实际是并行的）中发送邮件，则需要传父协程的日志id
     * @var string
     */
    protected $parentId;

    /**
     * 如果默认的模板id配置为数组，则此key决定使用哪个模板
     * 例如默认的模板id配置为： ['verify_code' => 111, 'warning' => 222] ,此key为verify_code则表示使用值为111的模板id
     * 简单使用示例： email(['templateKey' => 'Joyboo'])->send()
     * @var string|int
     */
    protected $templateKey = '';

}
