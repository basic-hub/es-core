<?php

namespace BasicHub\EsCore\Common\CloudLib\Notify\Message\WeCom;

class Image extends Base
{
    /**
     * 图片内容的base64编码
     * @var string
     */
    protected $base64 = '';

    /**
     * 图片内容（base64编码前）的md5值
     * @var string
     */
    protected $md5 = '';

    /**
     * 图片文件路径（如果提供，自动计算base64和md5）
     * @var string
     */
    protected $imagePath = '';

    public function fullData()
    {
        // 如果提供了图片路径，自动计算base64和md5
        if (!empty($this->imagePath) && file_exists($this->imagePath)) {
            $imageData = file_get_contents($this->imagePath);
            $this->base64 = base64_encode($imageData);
            $this->md5 = md5($imageData);
        }

        return [
            'msgtype' => 'image',
            'image' => [
                'base64' => $this->base64,
                'md5' => $this->md5
            ]
        ];
    }
}
