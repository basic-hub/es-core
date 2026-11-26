<?php

namespace BasicHub\EsCore\Common\Notify\Message\Slack;

use BasicHub\EsCore\Common\Classes\DateUtils;
use BasicHub\EsCore\Common\Notify\Message\MessageInterface;
use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements MessageInterface
{
    /**
     * 频道ID
     * @var string
     */
    protected $channel = '';

    /**
     * 用户名（机器人显示的名称）
     * @var string
     */
    protected $username = '';

    /**
     * 图标URL
     * @var string
     */
    protected $icon_url = '';

    /**
     * 是否以纯文本方式发送
     * @var bool
     */
    protected $mrkdwn = true;

    /**
     * 获取服务器信息文本
     * @return string
     */
    public function getServerText()
    {
        return PHP_EOL . implode(PHP_EOL, [
                '系统：' . config('SERVER_NAME'),
                '服务器：' . config('SERVNAME'),
                '服务器时间：' . date(DateUtils::FULL) . ' （' . DateUtils::getTimeZoneUTC() . '）'
            ]);
    }

    /**
     * 获取基础参数
     * @return array
     */
    protected function getBaseParams()
    {
        $params = [];

        if (!empty($this->channel)) {
            $params['channel'] = $this->channel;
        }

        if (!empty($this->username)) {
            $params['username'] = $this->username;
        }

        if (!empty($this->icon_url)) {
            $params['icon_url'] = $this->icon_url;
        }

        return $params;
    }
}
