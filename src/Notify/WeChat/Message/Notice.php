<?php

namespace BasicHub\EsCore\Notify\WeChat\Message;

class Notice extends Base
{
    protected $content = '';

    public function struct()
    {
        return [
            'keyword1' => ['value' => $this->content],
            'keyword2' => ['value' => APP_MODULE],
            'keyword3' => ['value' => config('SERVNAME')],
            'keyword4' => ['value' => date('Y年m月d日 H:i:s')],
        ];
    }
}
