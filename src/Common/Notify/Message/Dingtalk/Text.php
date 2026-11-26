<?php

namespace BasicHub\EsCore\Common\Notify\Message\Dingtalk;

class Text extends Base
{
    protected $content = '';

    public function fullData()
    {
        return [
            'msgtype' => 'text',
            'text' => [
                'content' => $this->content . $this->getServerText(),
            ],
            'at' => $this->getAtText(),
        ];
    }
}
