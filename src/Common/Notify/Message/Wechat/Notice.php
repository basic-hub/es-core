<?php

namespace BasicHub\EsCore\Common\Notify\Message\Wechat;

class Notice extends Base
{
    protected $content = '';

    public function fullData()
    {
        return [
            'keyword1' => ['value' => $this->content],
            'keyword2' => ['value' => config('SERVER_NAME')],
            'keyword3' => ['value' => config('SERVNAME')],
            'keyword4' => ['value' => date('Y年m月d日 H:i:s')],
        ];
    }
}
