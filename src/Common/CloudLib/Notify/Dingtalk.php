<?php

namespace BasicHub\EsCore\Common\CloudLib\Notify;

use EasySwoole\HttpClient\HttpClient;

/**
 * 钉钉通知驱动
 * @document https://open.dingtalk.com/document/group/custom-robot-access
 */
class Dingtalk extends Base
{
    /**
     * WebHook地址
     * @var string
     */
    protected $url = '';

    /**
     * 密钥
     * @var string
     */
    protected $signKey = '';

    /**
     * 要@哪些人（手机号）, true-所有人,false-谁也不鸟
     * @var array|bool
     */
    protected $at = false;

    /**
     * 实际发送消息
     * 每个机器人每分钟最多发送20条消息到群里，如果超过20条，会限流10分钟
     * 支持文本 (text)、链接 (link)、markdown(markdown)、ActionCard、FeedCard消息类型
     * @param MessageInterface $message
     * @return bool
     */
    public function send(MessageInterface $message): bool
    {
        if ($this->isdebug) {
            trace(__METHOD__ . ', message=' . var_export($message->fullData(), true));
            return true;
        }

        $data = $message->fullData();

        $url = $this->url;
        $secret = $this->signKey;

        // 签名 &timestamp=XXX&sign=XXX
        $timestamp = round(microtime(true), 3) * 1000;
        $sign = utf8_encode(urlencode(base64_encode(hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true))));

        $url .= "&timestamp={$timestamp}&sign={$sign}";

        $json = hcurl($url, $data, 'json')->json();
        if ($json['errcode'] !== 0) {
            trace("钉钉消息发送失败: message=" . $message->__toString() . ', response=' . json_encode($json, JSON_UNESCAPED_UNICODE), 'error');
            return false;
        }
        return true;
    }
}
