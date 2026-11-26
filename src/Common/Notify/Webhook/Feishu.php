<?php

namespace BasicHub\EsCore\Common\Notify\Webhook;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;

class Feishu extends Base
{
    /**
     * @document https://open.feishu.cn/document/client-docs/bot-v3/add-custom-bot
     * 自定义机器人的频率控制：100 次/分钟，5 次/秒
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

        if ($this->openSign) {
            $timestamp = time();
            $sign = base64_encode(hash_hmac('sha256', '', $timestamp . "\n" . $this->signKey, true));

            $data['timestamp'] = $timestamp;
            $data['sign'] = $sign;
        }

        return hcurl($this->url, $data, 'json')->json();
    }
}
