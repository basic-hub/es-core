<?php

namespace BasicHub\EsCore\Common\Notify\Message\Discord;

/**
 * Discord 文本消息
 * @document https://discord.com/developers/docs/resources/webhook#execute-webhook
 */
class Text extends Base
{
    /**
     * 消息内容
     * @var string
     */
    protected $content = '';

    public function fullData()
    {
        $data = array_merge($this->getBaseParams(), [
            'content' => $this->content . $this->getServerText(),
        ]);

        return $data;
    }

    public function __toString()
    {
        return $this->content;
    }
}
