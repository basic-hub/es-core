<?php

namespace BasicHub\EsCore\Notify\Feishu\Message;

/**
 * 基于飞书卡片实现的表格类，支持单表格、批量表格，批量表格支持为不同子表格设置不同子title
 */
class CardTable extends Base
{
    /**
     * 主标题
     * @var string
     */
    protected $title = '';

    /**
     * 标题标签,name => color
     * @var string
     */
    protected $titleTag = [];

    /**
     * 标题图标
     * @var string
     */
    protected $titleIcon = 'vote_colorful';

    /**
     * 标题颜色
     * @var string
     */
    protected $titleColor = 'blue';

    /**
     * 子标题，可选
     * @var string
     */
    protected $subTitle = '';

    /**
     * 表头key-value
     * @var string[]
     */
    protected $headers = [];

    /**
     * 表格二维数组,如果是批量表格则是三维数组,注意飞书卡片限制最多不能超过`5`张表格
     * @var array
     */
    protected $rows = [];

    protected $header_style = [];

    /**
     * 如果是批量表格，是否需要为每个表格展示子标题
     * @var bool
     */
    protected $rowSubTitle = true;

    /**
     * 每页数量
     * @var int
     */
    protected $pageSize = 8;

    protected $rowWidth = '80px';

    protected function getTags()
    {
        $tags = [];
        foreach ($this->titleTag as $name => $tagC) {
            $tags[] = [
                'tag' => 'text_tag',
                'text' => [
                    'tag' => 'plain_text',
                    'content' => $name
                ],
                'color' => $tagC
            ];
        }
        return $tags;
    }

    protected function getHeader()
    {
        $tags = $this->getTags();
        return [
            'zh_cn' => [
                'ud_icon' => [
                    'tag' => 'standard_icon',
                    'token' => $this->titleIcon,
                ],
                'template' => $this->titleColor,
                'title' => [
                    'tag' => 'plain_text',
                    'content' => $this->title,
                ],
                'subtitle' => [
                    'tag' => 'plain_text',
                    'content' => $this->subTitle,
                ],
                'text_tag_list' => $tags,
            ],
        ];
    }

    protected function getColumns()
    {
        $columns = [];
        foreach ($this->headers as $key => $value) {
            if (is_array($value)) {
                // 至少需要display_name
                $c = $value + [
                        'data_type' => 'text',
                        'name' => $key,
                        'horizontal_align' => 'left',
                        'width' => $this->rowWidth
                    ];
            } else {
                $c = [
                    'data_type' => 'text',
                    'name' => $key,
                    'display_name' => $value,
                    'horizontal_align' => 'left',
                    'width' => $this->rowWidth
                ];
            }

            $columns[] = $c;
        }
        return $columns;
    }


    protected function getTables()
    {
        $columns = $this->getColumns();

        $keys = array_keys($this->headers);

        $tables = [];
        foreach ($this->rows as $tt => $row) {
            if ($this->rowSubTitle) {
                $tables[] = [
                    'tag' => 'column_set',
                    'flex_mode' => 'none',
                    'horizontal_spacing' => 'default',
                    'background_style' => 'default',
                    'columns' => [
                        [
                            'tag' => 'column',
                            'width' => 'weighted',
                            'weight' => 1,
                            'elements' => [
                                [
                                    'tag' => 'div',
                                    'text' => [
                                        'tag' => 'plain_text',
                                        'content' => $tt,
                                        'text_size' => 'heading',
                                        'text_align' => 'left',
                                        'text_color' => 'green'
                                    ],
                                    'icon' => [
                                        'tag' => 'standard_icon',
                                        'token' => 'meego_colorful',
                                        'color' => 'green',
                                    ],
                                ]
                            ],
                        ],
                    ],
                ];
            }

            // 字段要与结构保持一致,否则会报错
            foreach ($row as &$item) {
                // 没有的要补充上去
                foreach ($keys as $key) {
                    $item[$key] = $item[$key] ?? '';
                }
                // 多余的要删掉
                $item = array_intersect_key($item, array_flip($keys));
            }

            $tables[] = [
                'tag' => 'table',
                'columns' => $columns,
                'rows' => $row,
                'row_height' => 'low',
                'header_style' => !empty($this->header_style) ? $this->header_style : [
                    'background_style' => 'none',
                    'bold' => true,
                    'lines' => 1
                ],
                'page_size' => $this->pageSize,
            ];
        }
        return $tables;
    }

    public function fullData()
    {
        $tables = $this->getTables();
        $data = [
            'config' => [
                'update_multi' => true
            ],
            'i18n_header' => $this->getHeader(),
            'i18n_elements' => [
                'zh_cn' => $tables,
            ],
        ];

        return [
            'msg_type' => 'interactive',
            'card' => $data,
        ];
    }
}
