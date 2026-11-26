<?php

namespace BasicHub\EsCore\Common\Notify\Webhook;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;

class Slack extends Base
{
    /**
     * Slack Incoming Webhooks
     * @document https://api.slack.com/messaging/webhooks
     * Rate limit: 1 message per second per webhook
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

        if (isset($result['error'])) {
            trace("Slack消息发送失败: message=" . $message->__toString() . ', response=' . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
        }

        return $result;
    }
}
