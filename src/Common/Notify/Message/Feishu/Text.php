<?php

namespace BasicHub\EsCore\Common\Notify\Message\Feishu;

class Text extends Base
{
    public function fullData()
    {
        return [
            'msg_type' => 'text',
            'content' => [
                'text' => $this->inner ? ($this->content . $this->getServerText() . $this->getAtText()) : $this->content,
            ],
        ];
    }
}
