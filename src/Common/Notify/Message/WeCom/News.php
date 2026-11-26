<?php

namespace BasicHub\EsCore\Common\Notify\Message\WeCom;

class News extends Base
{
    /**
     * 图文消息列表
     * 每个元素包含：title, description, url, picurl
     * @var array
     */
    protected $articles = [];

    public function fullData()
    {
        return [
            'msgtype' => 'news',
            'news' => [
                'articles' => $this->articles
            ]
        ];
    }
}
