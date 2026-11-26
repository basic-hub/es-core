<?php

namespace BasicHub\EsCore\Common\Notify\Webhook;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;

class Dingtalk extends Base
{
    /**
     * 自定义机器人 webhook 推送
     * @document https://open.dingtalk.com/document/group/custom-robot-access
     * 每个机器人每分钟最多发送20条消息到群里，如果超过20条，会限流10分钟
     * 支持文本 (text)、链接 (link)、markdown(markdown)、ActionCard、FeedCard消息类型
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
        $url = $this->url;

        if ($this->openSign) {
            $secret = $this->signKey;

            // 签名 &timestamp=XXX&sign=XXX
            $timestamp = round(microtime(true), 3) * 1000;
            $sign = utf8_encode(urlencode(base64_encode(hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true))));

            $url .= "&timestamp={$timestamp}&sign={$sign}";
        }

        $json = hcurl($url, $data, 'json')->json();
        if ($json['errcode'] !== 0) {
            trace("钉钉消息发送失败: message=" . $message->__toString() . ', response=' . json_encode($json, JSON_UNESCAPED_UNICODE), 'error');
        }
        return $json;
    }
}
