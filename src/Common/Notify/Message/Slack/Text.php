<?php

namespace BasicHub\EsCore\Common\Notify\Message\Slack;

/**
 * Slack 文本消息
 * @document https://api.slack.com/messaging/webhooks#posting_with_webhooks
 */
class Text extends Base
{
    /**
     * 消息文本
     * @var string
     */
    protected $text = '';

    public function fullData()
    {
        $data = array_merge($this->getBaseParams(), [
            'text' => $this->text . $this->getServerText(),
            'mrkdwn' => $this->mrkdwn,
        ]);

        return $data;
    }

    public function __toString()
    {
        return $this->text;
    }
}
