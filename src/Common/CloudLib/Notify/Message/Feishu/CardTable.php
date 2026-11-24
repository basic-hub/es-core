<?php

namespace BasicHub\EsCore\Common\CloudLib\Notify\Message\Feishu;

/**
 * Class CardTable  表格样式的卡片
 * @document https://open.feishu.cn/cardkit/editor?cardId=AAqj2ssPCTy5m&cardLocale=zh_cn
 * @package BasicHub\EsCore\Common\CloudLib\Notify\Message\Feishu
 */
class CardTable extends Base
{
    /**
     * 标题栏颜色
     * @var string
     */
    protected $titleColor = 'green';

    /**
     * 副标题，可不设置
     * @var string
     */
    protected $subTitle = '';

    /**
     * [ 单行数据 1，单行数据 2 ]
     * @var array
     */
    protected $rows = [];

    protected $formartRowsText = [];

    public function fullData()
    {
        $this->formartRows();
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
                    ...$this->formartRowsText,
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

    protected function formartRows()
    {
        foreach ($this->rows as $row) {
            $this->formartRowsText[] = $row->fullData();
        }
    }
}
