<?php

namespace BasicHub\EsCore\Common\Notify\Message\Dingtalk;

class Markdown extends Base
{
    protected $title = '';
    protected $text = '';

    public function fullData()
    {
        return [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $this->title,
                'text' => $this->text . $this->getServerText(),
            ],
            'at' => $this->getAtText(),
        ];
    }
}
