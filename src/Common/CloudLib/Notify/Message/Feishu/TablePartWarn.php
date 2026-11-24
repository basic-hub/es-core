<?php

namespace BasicHub\EsCore\Common\CloudLib\Notify\Message\Feishu;

use EasySwoole\Spl\SplBean;

/**
 * Class TablePartWarn 警告样式的一行数据
 * @package BasicHub\EsCore\Common\CloudLib\Notify\Message\Feishu
 */
class TablePartWarn extends SplBean
{
    protected $field1 = '';
    protected $field2 = '';
    protected $field3 = '';

    public function fullData()
    {
        return [
            "tag" => "column_set",
            "flex_mode" => "none",
            "horizontal_spacing" => "default",
            "background_style" => "default",
            "columns" => [
                [
                    "tag" => "column",
                    "elements" => [
                        [
                            "tag" => "markdown",
                            "content" => $this->field1,
                            "text_align" => "left",
                            "text_size" => "normal"
                        ]
                    ],
                    "width" => "weighted",
                    "weight" => 1
                ],
                [
                    "tag" => "column",
                    "elements" => [
                        [
                            "tag" => "markdown",
                            "content" => $this->field2,
                            "text_align" => "left",
                            "text_size" => "normal"
                        ]
                    ],
                    "width" => "weighted",
                    "weight" => 1
                ],
                [
                    "tag" => "column",
                    "elements" => [
                        [
                            "tag" => "markdown",
                            "content" => $this->field3,
                            "text_align" => "left",
                            "text_size" => "normal"
                        ]
                    ],
                    "width" => "weighted",
                    "weight" => 1
                ]
            ]
        ];
    }
}
