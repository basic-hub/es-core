<?php

namespace BasicHub\EsCore\Common\Notify\Message\WeCom;

/**
 * 企业微信文本消息
 * @document https://developer.work.weixin.qq.com/document/path/90236#%E6%96%87%E6%9C%AC%E6%B6%88%E6%81%AF
 */
class Text extends Base
{
    /**
     * 文本内容
     * @var string
     */
    protected $content = '';

    public function fullData()
    {
        $data = [
            'msgtype' => 'text',
            'text' => [
                'content' => $this->content,
            ],
        ];

        // 添加@相关参数
        $atParams = $this->getAtParams();
        if (!empty($atParams)) {
            $data['text'] = array_merge($data['text'], $atParams);
        }

        return $data;
    }

    public function __toString()
    {
        return $this->content;
    }
}
