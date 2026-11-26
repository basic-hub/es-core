<?php

namespace BasicHub\EsCore\Common\Notify\Webhook;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;

class Discord extends Base
{
    /**
     * Discord Webhooks
     * @document https://discord.com/developers/docs/resources/webhook
     * Rate limit: 30 requests per minute per webhook (with burst capacity of 5 requests)
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

        if (isset($result['code']) && isset($result['message'])) {
            trace("Discord消息发送失败: message=" . $message->__toString() . ', response=' . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
        }

        return $result;
    }
}
