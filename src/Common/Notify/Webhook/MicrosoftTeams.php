<?php

namespace BasicHub\EsCore\Common\Notify\Webhook;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;

class MicrosoftTeams extends Base
{
    /**
     * Microsoft Teams Incoming Webhooks
     * @document https://learn.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook
     * Rate limit: 4 requests per second per app per tenant
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
            trace("Microsoft Teams消息发送失败: message=" . $message->__toString() . ', response=' . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
        }

        return $result;
    }
}
