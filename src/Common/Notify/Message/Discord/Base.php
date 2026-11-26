<?php

namespace BasicHub\EsCore\Common\Notify\Message\Discord;

use BasicHub\EsCore\Common\Classes\DateUtils;
use BasicHub\EsCore\Common\Notify\Message\MessageInterface;
use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements MessageInterface
{
    /**
     * 用户名（覆盖webhook默认名称）
     * @var string
     */
    protected $username = '';

    /**
     * 头像URL（覆盖webhook默认头像）
     * @var string
     */
    protected $avatar_url = '';

    /**
     * Text-to-Speech
     * @var bool
     */
    protected $tts = false;

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

        if (!empty($this->username)) {
            $params['username'] = $this->username;
        }

        if (!empty($this->avatar_url)) {
            $params['avatar_url'] = $this->avatar_url;
        }

        if ($this->tts) {
            $params['tts'] = true;
        }

        return $params;
    }
}
