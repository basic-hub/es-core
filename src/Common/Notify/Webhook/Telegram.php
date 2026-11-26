<?php

namespace BasicHub\EsCore\Common\Notify\Webhook;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;

class Telegram extends Base
{
    /**
     * Telegram Bot API - sendMessage
     * @document https://core.telegram.org/bots/api#sendmessage
     * Rate limit: 30 messages per second (across all chats)
     * 注意: Telegram webhook 需要格式为 https://api.telegram.org/bot{token}/sendMessage
     * @param MessageInterface $message
     * @return array|mixed|object
     */
    public function send(MessageInterface $message)
    {
        if ($this->isdebug) {
            trace(__METHOD__ . ', message=' . var_export($message->fullData(), true));
            return true;
        }

        $data = $message->fullData();
        $result = hcurl($this->url, $data, 'json')->json();

        if (isset($result['ok']) && $result['ok'] === false) {
            trace("Telegram消息发送失败: message=" . $message->__toString() . ', response=' . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
        }

        return $result;
    }
}
