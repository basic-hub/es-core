<?php

namespace BasicHub\EsCore\Common\Notify\Message\Telegram;

/**
 * Telegram 文本消息
 * @document https://core.telegram.org/bots/api#sendmessage
 */
class Text extends Base
{
    /**
     * 消息文本
     * @var string
     */
    protected $text = '';

    /**
     * 解析模式: Markdown, MarkdownV2, HTML
     * @var string
     */
    protected $parse_mode = '';

    public function fullData()
    {
        $data = array_merge($this->getBaseParams(), [
            'text' => $this->text . $this->getServerText(),
        ]);

        if (!empty($this->parse_mode)) {
            $data['parse_mode'] = $this->parse_mode;
        }

        return $data;
    }

    public function __toString()
    {
        return $this->text;
    }
}
