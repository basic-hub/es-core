<?php

namespace BasicHub\EsCore\Common\Notify\Message\Discord;

/**
 * Discord Embed 富媒体消息
 * @document https://discord.com/developers/docs/resources/channel#embed-object
 */
class Embed extends Base
{
    /**
     * 消息内容
     * @var string
     */
    protected $content = '';

    /**
     * Embed 结构数组
     * @var array
     */
    protected $embeds = [];

    public function fullData()
    {
        $data = array_merge($this->getBaseParams(), [
            'content' => $this->content,
            'embeds' => $this->embeds,
        ]);

        return $data;
    }

    public function __toString()
    {
        return $this->content;
    }
}
