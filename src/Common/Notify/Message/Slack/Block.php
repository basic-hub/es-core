<?php

namespace BasicHub\EsCore\Common\Notify\Message\Slack;

/**
 * Slack Block Kit 消息
 * @document https://api.slack.com/block-kit
 */
class Block extends Base
{
    /**
     * 消息文本（作为通知预览）
     * @var string
     */
    protected $text = '';

    /**
     * Block Kit 结构
     * @var array
     */
    protected $blocks = [];

    public function fullData()
    {
        $data = array_merge($this->getBaseParams(), [
            'text' => $this->text,
            'blocks' => $this->blocks,
        ]);

        return $data;
    }

    public function __toString()
    {
        return $this->text;
    }
}
