<?php

namespace BasicHub\EsCore\Common\Notify\Message\Dingtalk;

use BasicHub\EsCore\Common\Classes\DateUtils;
use BasicHub\EsCore\Common\Notify\Message\MessageInterface;
use EasySwoole\Spl\SplBean;

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
                '系统：' . config('SERVER_NAME'),
                '服务器：' . config('SERVNAME'),
                '服务器时间：' . date(DateUtils::FULL) . ' （' . DateUtils::getTimeZoneUTC() . '）'
            ]);
    }
}
