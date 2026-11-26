<?php

namespace BasicHub\EsCore\Common\Notify\Message\MicrosoftTeams;

/**
 * Microsoft Teams 文本消息
 * @document https://learn.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/connectors-using#send-messages-using-curl-and-powershell
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
        return [
            'type' => $this->type,
            'text' => $this->text . $this->getServerText(),
        ];
    }

    public function __toString()
    {
        return $this->text;
    }
}
