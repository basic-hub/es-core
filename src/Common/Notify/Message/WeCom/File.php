<?php

namespace BasicHub\EsCore\Common\Notify\Message\WeCom;

class File extends Base
{
    /**
     * 文件ID，通过uploadMedia上传文件获得
     * @var string
     */
    protected $media_id = '';

    public function fullData()
    {
        return [
            'msgtype' => 'file',
            'file' => [
                'media_id' => $this->media_id
            ]
        ];
    }
}
