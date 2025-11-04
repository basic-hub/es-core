<?php

namespace BasicHub\EsCore\Notify\Feishu\Message;

class Text extends Base
{
    public function fullData()
    {
        return [
            'msg_type' => 'text',
            'content' => [
                'text' => $this->inner ? $this->getAtText($this->getServerText($this->content)) : $this->content,
            ],
        ];
    }
}
