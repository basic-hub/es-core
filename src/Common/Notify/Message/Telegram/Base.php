<?php

namespace BasicHub\EsCore\Common\Notify\Message\Telegram;

use BasicHub\EsCore\Common\Classes\DateUtils;
use BasicHub\EsCore\Common\Notify\Message\MessageInterface;
use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements MessageInterface
{
    /**
     * 聊天ID（必填）
     * @var string|int
     */
    protected $chat_id = '';

    /**
     * 是否禁用链接预览
     * @var bool
     */
    protected $disable_web_page_preview = false;

    /**
     * 是否禁用通知
     * @var bool
     */
    protected $disable_notification = false;

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
        $params = [
            'chat_id' => $this->chat_id,
        ];

        if ($this->disable_web_page_preview) {
            $params['disable_web_page_preview'] = true;
        }

        if ($this->disable_notification) {
            $params['disable_notification'] = true;
        }

        return $params;
    }
}
