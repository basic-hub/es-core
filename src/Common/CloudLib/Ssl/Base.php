<?php

namespace BasicHub\EsCore\Common\CloudLib\Ssl;

use EasySwoole\Spl\SplBean;
use EasySwoole\Utility\ArrayToTextTable;

abstract class Base extends SplBean implements SslInterface
{
    protected $echo = true;

    /**
     * 将内容输出至控制台
     * @param array $header 表头，一维数组，示例: [name => 'Joyboo']
     * @param array $data 数据，二维数组
     * @return void
     */
    protected function echo($header, $data)
    {
        echo PHP_EOL;

        $Table = new ArrayToTextTable();

        $Table->isDisplayHeader(false);

        array_unshift($data, $header);

        echo $Table->getTable($data);
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
}
