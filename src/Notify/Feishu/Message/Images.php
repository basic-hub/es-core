<?php

namespace BasicHub\EsCore\Notify\Feishu\Message;

class Images extends Base
{
    protected $imageKey= '';

    public function fullData()
    {
        $data = [
            'msg_type' => 'image',
            'content' => [
                'image_key' => $this->imageKey,
            ],
        ];
        return $data;
    }
}
