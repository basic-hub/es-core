<?php

namespace BasicHub\EsCore\Common\Notify\Message\Telegram;

/**
 * Telegram Markdown 消息
 * @document https://core.telegram.org/bots/api#markdown-style
 */
class Markdown extends Base
{
    /**
     * 消息文本（Markdown格式）
     * @var string
     */
    protected $text = '';

    public function fullData()
    {
        $data = array_merge($this->getBaseParams(), [
            'text' => $this->text . $this->getServerText(),
            'parse_mode' => 'MarkdownV2',
        ]);

        return $data;
    }

    public function __toString()
    {
        return $this->text;
    }
}
