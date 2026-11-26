<?php

namespace BasicHub\EsCore\Common\Notify\Message\MicrosoftTeams;

/**
 * Microsoft Teams MessageCard 消息
 * @document https://learn.microsoft.com/en-us/microsoftteams/platform/task-modules-and-cards/cards/cards-reference#office-365-connector-card
 */
class Card extends Base
{
    /**
     * 消息类型（MessageCard）
     * @var string
     */
    protected $type = 'MessageCard';

    /**
     * JSON-LD 上下文
     * @var string
     */
    protected $context = 'https://schema.org/extensions';

    /**
     * 卡片标题
     * @var string
     */
    protected $title = '';

    /**
     * 卡片摘要
     * @var string
     */
    protected $summary = '';

    /**
     * 卡片文本
     * @var string
     */
    protected $text = '';

    /**
     * 主题颜色
     * @var string
     */
    protected $themeColor = '';

    /**
     * sections 区域
     * @var array
     */
    protected $sections = [];

    /**
     * potentialAction 按钮
     * @var array
     */
    protected $potentialAction = [];

    public function fullData()
    {
        $data = [
            '@type' => $this->type,
            '@context' => $this->context,
        ];

        if (!empty($this->title)) {
            $data['title'] = $this->title;
        }

        if (!empty($this->summary)) {
            $data['summary'] = $this->summary;
        }

        if (!empty($this->text)) {
            $data['text'] = $this->text . $this->getServerText();
        }

        if (!empty($this->themeColor)) {
            $data['themeColor'] = $this->themeColor;
        }

        if (!empty($this->sections)) {
            $data['sections'] = $this->sections;
        }

        if (!empty($this->potentialAction)) {
            $data['potentialAction'] = $this->potentialAction;
        }

        return $data;
    }

    public function __toString()
    {
        return $this->title . ': ' . $this->text;
    }
}
