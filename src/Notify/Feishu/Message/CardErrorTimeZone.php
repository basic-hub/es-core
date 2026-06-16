<?php

namespace BasicHub\EsCore\Notify\Feishu\Message;

/**
 * 带时区的异常推送卡片
 */
class CardErrorTimeZone extends CardError
{
    /**
     * UTC+8:00 时区时间
     * @var string
     */
    protected $datetime8 = '';

    /**
     * 服务器时区的时间
     * @var string
     */
    protected $datetimeLocal = '';

    /**
     * 服务器时区，内容格式： UTC-5:00、UTC+8:00
     * @var string
     */
    protected $timeZone = '';

    public function fullData()
    {
        $atAllText = [
            'tag' => 'div',
            'text' => [
                'tag' => 'lark_md',
                'content' => $this->getAtText(),
            ],
        ];

        $data = [
            "i18n_elements" => [
                "zh_cn" => [
                    [
                        'tag' => 'column_set',
                        'horizontal_spacing' => '8px',
                        'horizontal_align' => 'left',
                        'columns' => [
                            [
                                'tag' => 'column',
                                'width' => 'weighted',
                                'elements' => [
                                    [
                                        'tag' => 'column_set',
                                        'horizontal_spacing' => '8px',
                                        'horizontal_align' => 'left',
                                        'columns' => [
                                            [
                                                'tag' => 'column',
                                                'width' => 'weighted',
                                                'elements' => [
                                                    [
                                                        'tag' => 'markdown',
                                                        'content' => "** 服务器：**\n{$this->servername}",
                                                        'text_align' => 'left',
                                                        'text_size' => 'normal'
                                                    ]
                                                ],
                                                'vertical_align' => 'top',
                                                'weight' => 1
                                            ],
                                            [
                                                'tag' => 'column',
                                                'width' => 'weighted',
                                                'elements' => [
                                                    [
                                                        'tag' => 'markdown',
                                                        'content' => "** 项目：**\n{$this->project}",
                                                        'text_align' => 'left',
                                                        'text_size' => 'normal'
                                                    ]
                                                ],
                                                'vertical_align' => 'top',
                                                'weight' => 1
                                            ],
                                            [
                                                'tag' => 'column',
                                                'width' => 'weighted',
                                                'elements' => [
                                                    [
                                                        'tag' => 'markdown',
                                                        'content' => "** 触发方式：**\n{$this->trigger}",
                                                        'text_align' => 'left',
                                                        'text_size' => 'normal'
                                                    ]
                                                ],
                                                'vertical_align' => 'top',
                                                'weight' => 1
                                            ]
                                        ],
                                        'margin' => '16px 0px 0px 0px'
                                    ]
                                ],
                                'vertical_align' => 'top',
                                'vertical_spacing' => '8px',
                                'weight' => 1
                            ]
                        ],
                        'margin' => '16px 0px 0px 0px'
                    ],
                    [
                        'tag' => 'column_set',
                        'horizontal_spacing' => '8px',
                        'horizontal_align' => 'left',
                        'columns' => [
                            [
                                'tag' => 'column',
                                'width' => 'weighted',
                                'elements' => [
                                    [
                                        'tag' => 'column_set',
                                        'horizontal_spacing' => '8px',
                                        'horizontal_align' => 'left',
                                        'columns' => [
                                            [
                                                'tag' => 'column',
                                                'width' => 'weighted',
                                                'elements' => [
                                                    [
                                                        'tag' => 'markdown',
                                                        'content' => "**UTC+8:00：**\n{$this->datetime8}",
                                                        'text_align' => 'left',
                                                        'text_size' => 'normal'
                                                    ]
                                                ],
                                                'vertical_align' => 'top',
                                                'weight' => 1
                                            ],
                                            [
                                                'tag' => 'column',
                                                'width' => 'weighted',
                                                'elements' => [
                                                    [
                                                        'tag' => 'markdown',
                                                        'content' => "**{$this->timeZone}：**\n{$this->datetimeLocal}",
                                                        'text_align' => 'left',
                                                        'text_size' => 'normal'
                                                    ]
                                                ],
                                                'vertical_align' => 'top',
                                                'weight' => 1
                                            ],
                                            [
                                                'tag' => 'column',
                                                'width' => 'weighted',
                                                'elements' => [
                                                    [
                                                        'tag' => 'markdown',
                                                        'content' => "**服务器时区：**\n{$this->timeZone}",
                                                        'text_align' => 'left',
                                                        'text_size' => 'normal'
                                                    ]
                                                ],
                                                'vertical_align' => 'top',
                                                'weight' => 1
                                            ]
                                        ],
                                        'margin' => '16px 0px 0px 0px'
                                    ]
                                ],
                                'vertical_align' => 'top',
                                'vertical_spacing' => '8px',
                                'weight' => 1
                            ]
                        ],
                        'margin' => '16px 0px 0px 0px'
                    ],
                    [
                        'tag' => 'hr'
                    ],
                    [
                        'tag' => 'column_set',
                        'horizontal_spacing' => '8px',
                        'horizontal_align' => 'left',
                        'columns' => [
                            [
                                'tag' => 'column',
                                'width' => 'weighted',
                                'elements' => [
                                    [
                                        'tag' => 'column_set',
                                        'flex_mode' => 'none',
                                        'horizontal_spacing' => 'default',
                                        'background_style' => 'default',
                                        'columns' => [
                                            [
                                                'tag' => 'column',
                                                'elements' => [
                                                    [
                                                        'tag' => 'div',
                                                        'text' => [
                                                            'tag' => 'plain_text',
                                                            'content' => "文件: {$this->filename}",
                                                            'text_size' => 'normal',
                                                            'text_align' => 'left',
                                                            'text_color' => 'default'
                                                        ],
                                                        'icon' => [
                                                            'tag' => 'standard_icon',
                                                            'token' => 'file-link-word_outlined',
                                                            'color' => 'grey'
                                                        ]
                                                    ]
                                                ],
                                                'width' => 'weighted',
                                                'weight' => 1
                                            ]
                                        ]
                                    ]
                                ],
                                'vertical_align' => 'top',
                                'vertical_spacing' => '8px',
                                'weight' => 1
                            ]
                        ],
                        'margin' => '16px 0px 0px 0px'
                    ],
                    [
                        'tag' => 'column_set',
                        'flex_mode' => 'none',
                        'horizontal_spacing' => 'default',
                        'background_style' => 'default',
                        'columns' => [
                            [
                                'tag' => 'column',
                                'elements' => [
                                    [
                                        'tag' => 'div',
                                        'text' => [
                                            'tag' => 'plain_text',
                                            'content' => "详情: {$this->content}",
                                            'text_size' => 'normal',
                                            'text_align' => 'left',
                                            'text_color' => 'default'
                                        ],
                                        'icon' => [
                                            'tag' => 'standard_icon',
                                            'token' => 'tab-more_outlined',
                                            'color' => 'grey'
                                        ]
                                    ]
                                ],
                                'width' => 'weighted',
                                'weight' => 1
                            ]
                        ]
                    ],
                    ...($this->isAtAll ? [$atAllText] : [])
                ]
            ],
            "i18n_header" => [
                "zh_cn" => [
                    "title" => [
                        "tag" => "plain_text",
                        "content" => $this->title
                    ],
                    "subtitle" => [
                        "tag" => "plain_text",
                        "content" => $this->subTitle
                    ],
                    "template" => $this->titleColor
                ]
            ]
        ];

        return [
            'msg_type' => 'interactive',
            'card' => $data,
        ];
    }
}
