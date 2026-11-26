<?php

namespace BasicHub\EsCore\Common\Notify\Message\WeCom;

class Markdown extends Base
{
    /**
     * markdown 内容
     * @var string
     */
    protected $content = '';

    public function fullData()
    {
        // 添加 @ 相关参数
        $atParams = $this->getAtParams();

        return [
            'msgtype' => 'markdown',
            'markdown' => [
                'content' => $this->content . $atParams
            ]
        ];
    }
}
