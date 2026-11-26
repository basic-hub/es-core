<?php

namespace BasicHub\EsCore\Common\Notify\Message\WeCom;

class TextCard extends Base
{
    /**
     * 标题
     * @var string
     */
    protected $title = '';

    /**
     * 描述，支持换行、以及A标签
     * @var string
     */
    protected $description = '';

    /**
     * 点击后跳转的链接
     * @var string
     */
    protected $url = '';

    /**
     * 按钮文字，默认为"详情"
     * @var string
     */
    protected $btntxt = '详情';

    public function fullData()
    {
        return [
            'msgtype' => 'textcard',
            'textcard' => [
                'title' => $this->title,
                'description' => $this->description,
                'url' => $this->url,
                'btntxt' => $this->btntxt
            ]
        ];
    }
}
