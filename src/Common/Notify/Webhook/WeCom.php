<?php

namespace BasicHub\EsCore\Common\Notify\Webhook;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;

class WeCom extends Base
{

    /**
     * 群机器人 webhook 推送
     * @document https://developer.work.weixin.qq.com/document/path/91770
     * 每个机器人发送的消息不能超过20条/分钟
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

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            trace("企业微信群机器人消息发送失败: message=" . $message->__toString() . ', response=' . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
        }

        return $result;
    }
}

