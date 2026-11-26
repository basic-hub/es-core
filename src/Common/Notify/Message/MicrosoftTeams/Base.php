<?php

namespace BasicHub\EsCore\Common\Notify\Message\MicrosoftTeams;

use BasicHub\EsCore\Common\Classes\DateUtils;
use BasicHub\EsCore\Common\Notify\Message\MessageInterface;
use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements MessageInterface
{
    /**
     * 消息类型
     * @var string
     */
    protected $type = 'message';

    /**
     * 获取服务器信息文本
     * @return string
     */
    public function getServerText()
    {
        return "\n\n" . implode("\n\n", [
                '**系统：**' . config('SERVER_NAME'),
                '**服务器：**' . config('SERVNAME'),
                '**服务器时间：**' . date(DateUtils::FULL) . ' （' . DateUtils::getTimeZoneUTC() . '）'
            ]);
    }
}
