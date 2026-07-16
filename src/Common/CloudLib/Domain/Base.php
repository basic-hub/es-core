<?php

namespace BasicHub\EsCore\Common\CloudLib\Domain;

use EasySwoole\Spl\SplBean;
use EasySwoole\Utility\ArrayToTextTable;

abstract class Base extends SplBean implements DomainInterface
{
    protected $echo = true;

    /**
     * echoTable时，剩余时间小于多少天数的就标红
     * @var int
     */
    protected $echoRedDays = 30;

    /**
     * 将内容输出至控制台
     * @param array $header 表头，一维数组
     * @param array $data 数据，二维数组
     * @param array $rowColors 可选，与 $data 等长的颜色码数组
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

        // 着色策略：只对单元格内容着色，边框字符 │ 保持原色
        $lines = explode(PHP_EOL, $table);
        $contentIndex = -1;
        foreach ($lines as $i => $line) {
            if (strpos($line, '│') === 0) {
                $contentIndex++;
                // 跳过表头（contentIndex=0），对数据行着色
                if ($contentIndex >= 1) {
                    $dataIdx = $contentIndex - 1;
                    if (isset($rowColors[$dataIdx]) && $rowColors[$dataIdx] !== '') {
                        $color = $rowColors[$dataIdx];
                        $reset = "\033[0m";
                        $parts = explode('│', $line);
                        foreach ($parts as $j => $part) {
                            if ($j > 0 && $j < count($parts) - 1) {
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
     * 计算域名总有效天数（到期日 - 注册日）
     * @param string $beginTime 注册时间
     * @param string $endTime   到期时间
     * @return int 天数，解析失败返回 0
     */
    protected function calcDomainDays($beginTime, $endTime)
    {
        $begin = $beginTime ? strtotime($beginTime) : 0;
        $end = $endTime ? strtotime($endTime) : 0;
        if (!$begin || !$end || $end <= $begin) {
            return 0;
        }
        return intval(round(($end - $begin) / 86400));
    }

    /**
     * 计算剩余有效天数（到期日 - 今天）
     * @param string $endTime 到期时间
     * @return int|string 剩余天数（int）；已过期返回字符串 "已过期"
     */
    protected function calcRemainDays($endTime)
    {
        $end = $endTime ? strtotime($endTime) : 0;
        if (!$end) {
            return 0;
        }
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
     * @param int|string $remain 剩余天数
     * @return string
     */
    protected function rowColor($remain)
    {
        if ($remain === '已过期') {
            return "\033[38;5;245m";
        }
        if (is_int($remain) && $remain <= $this->echoRedDays) {
            return "\033[1;31m";
        }
        return '';
    }
}
