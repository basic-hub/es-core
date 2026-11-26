<?php

namespace BasicHub\EsCore\Common\Notify\Message\WeCom;

class Table extends Base
{
    /**
     * 标题
     * @var string
     */
    protected $title = '';

    /**
     * 表头key-value
     * @var string[]
     */
    protected $headers = [];

    /**
     * 表格二维数组,如果是批量表格则是三维数组
     * @var array
     */
    protected $rows = [];

    /**
     * 如果是批量表格，是否需要为每个表格展示子标题
     * @var bool
     */
    protected $rowSubTitle = true;

    public function fullData()
    {
        $markdownContent = "### {$this->title} \n\n";
        $keys = array_keys($this->headers);

        foreach ($this->rows as $tt => $row) {
            // 字段要与结构保持一致,否则会报错
            foreach ($row as &$item) {
                // 没有的要补充上去
                foreach ($keys as $key) {
                    $item[$key] = $item[$key] ?? '';
                }
                // 多余的要删掉
                $item = array_intersect_key($item, array_flip($keys));
            }

            if ($this->rowSubTitle) {
                // 表格子标题
                $markdownContent .= "#### {$tt}\n";
            }

            // 构造表头（如：| 序号 | 订单号 | 金额 |）
            $headerStr = "| " . implode(" | ", array_values($this->headers)) . " |\n";
            $markdownContent .= $headerStr;

            // 构造分隔线（如：| ---- | ------ | ---- |）
            $splitLine = "| " . implode(" | ", array_fill(0, count($this->headers), "----")) . " |\n";
            $markdownContent .= $splitLine;

            // 构造表体
            foreach ($row as $r) {
                $rowStr = "| " . implode(" | ", $r) . " |\n";
                $markdownContent .= $rowStr;
            }

            $markdownContent .= "\n"; // 表格之间空一行，分隔更清晰
        }

        // 添加 @ 相关参数
        $atParams = $this->getAtParams();

        $result = [
            "msgtype" => "markdown",
            "markdown" => [
                "content" => $markdownContent . $atParams
            ]
        ];

        return $result;
    }
}
