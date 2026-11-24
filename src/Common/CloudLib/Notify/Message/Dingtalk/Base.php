<?php

namespace BasicHub\EsCore\Common\CloudLib\Notify\Message\Dingtalk;

use BasicHub\EsCore\Common\Classes\DateUtils;
use EasySwoole\Spl\SplBean;
use BasicHub\EsCore\Common\CloudLib\Notify\MessageInterface;

abstract class Base extends SplBean implements MessageInterface
{
    /**
     * 被@人的手机号
     * @var array
     */
    protected $atMobiles = [];

    /**
     * 被@人的用户userid
     * @var array
     */
    protected $atUserIds = [];

    protected $isAtAll = false;

    public function getAtText()
    {
        $at = [
            'atMobiles' => $this->atMobiles,
            'atUserIds' => $this->atUserIds,
            'isAtAll' => $this->isAtAll
        ];
        return $at;
    }

    public function getServerText()
    {
        return PHP_EOL . implode(PHP_EOL, [
                '系统：' . APP_MODULE,
                '服务器：' . config('SERVNAME'),
                '服务器时间：' . date(DateUtils::FULL) . ' （' . DateUtils::getTimeZoneUTC() . '）'
            ]);
    }
}
