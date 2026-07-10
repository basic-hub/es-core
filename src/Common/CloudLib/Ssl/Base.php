<?php

namespace BasicHub\EsCore\Common\CloudLib\Ssl;

use EasySwoole\Spl\SplBean;
use EasySwoole\Utility\ArrayToTextTable;

abstract class Base extends SplBean implements SslInterface
{
    protected $echo = true;

    /**
     * echoTable时，剩余时间小于多少天数的就标红
     * @var int
     */
    protected $echoRedDays = 30;

    /**
     * 将内容输出至控制台
     * @param array $header 表头，一维数组，示例: [name => 'Joyboo']
     * @param array $data 数据，二维数组
     * @param array $rowColors 可选，与 $data 等长的颜色码数组，每个元素为 ANSI 颜色码或空字符串
     * @return void
     */
    protected function echo($header, $data, array $rowColors = [])
    {
        echo PHP_EOL;

        $Table = new ArrayToTextTable();

        $Table->isDisplayHeader(false);

        array_unshift($data, $header);

        // 先渲染纯文本表格（保证列宽按可见字符计算，对齐正确）
        $table = $Table->getTable($data);

        // 若无着色需求直接输出
        if (empty($rowColors)) {
            echo $table;
            return;
        }

        // 表格输出结构：边框行 与 内容行 交替。内容行以 │ 开头。
        // 第一个内容行（index=1）是表头，之后的内容行依次对应 $data 的数据行。
        // 着色策略：只对单元格内容着色，边框字符 │ 保持原色，避免边框变色。
        $lines = explode(PHP_EOL, $table);
        $contentIndex = -1; // 表头算第0个内容行，数据行从1开始
        foreach ($lines as $i => $line) {
            if (strpos($line, '│') === 0) {
                $contentIndex++;
                // 跳过表头（contentIndex=0），对数据行着色
                if ($contentIndex >= 1) {
                    $dataIdx = $contentIndex - 1;
                    if (isset($rowColors[$dataIdx]) && $rowColors[$dataIdx] !== '') {
                        $color = $rowColors[$dataIdx];
                        $reset = "\033[0m";
                        // 按 │ 切分：首元素为空（行首│之前），后续元素为 " 内容 "
                        $parts = explode('│', $line);
                        foreach ($parts as $j => $part) {
                            if ($j > 0 && $j < count($parts) - 1) {
                                // 中间段是单元格内容（含两侧空格），着色
                                $parts[$j] = $color . $part . $reset;
                            }
                        }
                        $lines[$i] = implode('│', $parts);
                    }
                }
            }
        }
        echo implode(PHP_EOL, $lines);
    }

    /**
     * 计算证书有效天数（到期日 - 开始日）
     * @param string $beginTime 开始时间，如 2024-11-06 08:00:00 或 2024-11-06
     * @param string $endTime   到期时间
     * @return int 天数，解析失败返回 0
     */
    protected function calcCertDays($beginTime, $endTime)
    {
        $begin = $beginTime ? strtotime($beginTime) : 0;
        $end = $endTime ? strtotime($endTime) : 0;
        if (!$begin || !$end || $end <= $begin) {
            return 0;
        }
        return intval(round(($end - $begin) / 86400));
    }

    /**
     * 计算剩余有效期天数（到期日 - 今天）
     * @param string $endTime 到期时间，如 2025-12-02 07:59:59 或 2025-12-02
     * @return int|string 剩余天数（int）；已过期返回字符串 "已过期"
     */
    protected function calcRemainDays($endTime)
    {
        $end = $endTime ? strtotime($endTime) : 0;
        if (!$end) {
            return 0;
        }
        // 今天 00:00:00 的时间戳
        $today = strtotime(date('Y-m-d'));
        $diff = $end - $today;
        if ($diff <= 0) {
            return '已过期';
        }
        return intval(round($diff / 86400));
    }

    /**
     * 根据剩余天数获取整行 ANSI 颜色码
     * - 已过期：灰色
     * - 不足30天：醒目红色（加粗）
     * - 其他：空字符串（不着色）
     * @param int|string $remain 剩余天数（calcRemainDays 的返回值）
     * @return string
     */
    protected function rowColor($remain)
    {
        if ($remain === '已过期') {
            // 中等亮度灰色（256色码245）
            return "\033[38;5;245m";
        }
        if (is_int($remain) && $remain <= $this->echoRedDays) {
            // 醒目红色（加粗）
            return "\033[1;31m";
        }
        return '';
    }
}
