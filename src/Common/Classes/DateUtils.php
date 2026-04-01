<?php


namespace BasicHub\EsCore\Common\Classes;


class DateUtils
{
    const YMD = 'ymd';
    const FULL = 'Y-m-d H:i:s';
    const YmdHis = 'YmdHis';
    const FMT_1 = 'Y-m-d';

    public static function format($time, $fmt = '')
    {
        if ( ! is_numeric($time)) {
            $time = strtotime($time);
        }
        if (empty($fmt)) {
            $fmt = self::FULL;
        }
        return date($fmt, $time);
    }

    /**
     * 将指定时区的格式化时间转换为时间戳
     * @param string $datetime  格式化时间，如 2026-03-18 16:06:52
     * @param string $inia  时区标识，如 Asia/Shanghai、America/Bogota
     * @return int              UTC 时间戳
     */
    public static function datetimeToTimestamp(string $datetime, string $inia): int
    {
        return (new \DateTime($datetime, new \DateTimeZone($inia)))->getTimestamp();
    }

    /**
     * 将时间戳转换为指定时区的格式化时间
     * @param int $timestamp    UTC 时间戳
     * @param string $inia      时区标识，如 Asia/Shanghai、America/Bogota、PRC
     * @param string $format
     * @return string
     */
    public static function timestampToDatetime(int $timestamp, $inia = 'PRC', $format = self::FULL): string
    {
        return (new \DateTime('@' . $timestamp))
            ->setTimezone(new \DateTimeZone($inia))
            ->format($format);
    }

    /**
     * 判断当前 PHP 运行时区是否与指定时区一致
     * @param string $inia 支持：Asia/Shanghai、+8:00、+08:00、8、-5 等
     * @return bool
     */
    public static function isInTimeZone(string $inia): bool
    {
        if (preg_match('/^([+-]?\d{1,2})(?::(\d{2}))?$/', trim($inia), $m)) {
            $sign   = ($m[1][0] === '-') ? -1 : 1;
            $target = $sign * (abs((int)$m[1]) * 3600 + ((int)($m[2] ?? 0)) * 60);
        } else {
            $target = (new \DateTimeZone($inia))->getOffset(new \DateTime('now', new \DateTimeZone('UTC')));
        }
        return (int)date('Z') === $target;
    }

    /**
     * 将两个时间戳转固定时区格式化，然后比较天数（按自然日）
     * @param int $time1
     * @param int $time2
     * @param string $inia Asia/Shanghai
     * @return int
     */
    public static function formatDiffTimestamp(int $time1, int $time2, $inia = 'PRC'): int
    {
        $tz = new \DateTimeZone($inia);
        $dt1 = (new \DateTime('@' . $time1))->setTimezone($tz)->setTime(0, 0);
        $dt2 = (new \DateTime('@' . $time2))->setTimezone($tz)->setTime(0, 0);
        return $dt1->diff($dt2)->days;
    }

    /**
     * 将ymd 转换为客户端展示的Y-m-d格式，如果在客户端转换，会误杀合计行
     */
    public static function ymdToClientFormat(string $ymd): string
    {
        if ( ! is_numeric($ymd)) {
            return $ymd;
        }
        $len = strlen($ymd);
        $array = str_split($ymd, 2);
        $join = implode('-', $array);
        return $len === 6 ? ('20' . $join) : $join;
    }
}
