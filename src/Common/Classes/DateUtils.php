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
     * 当前系统时区与指定时区之间的差值,单位秒
     * @param string $inia Asia/Shanghai
     */
    public static function timeZoneOffsetSec(string $inia)
    {
        $date = date(self::FULL);
        // 当前系统运行的时区
        $currentRunTimeZone = date_default_timezone_get();
        $currTimeZone = new \DateTimeZone($currentRunTimeZone);
        $currentOffset = $currTimeZone->getOffset(new \DateTime($date));

        $toTimeZone = new \DateTimeZone($inia);
        $toOffset = $toTimeZone->getOffset(new \DateTime($date));

        return $currentOffset - $toOffset;
    }

    public static function getTimeZoneStamp(int $time, $inia): int
    {
        return $time - self::timeZoneOffsetSec($inia);
    }

    /**
     * 将指定时区的格式化时间转换为 UTC 时间戳
     *
     * @param string $datetime  格式化时间，如 2026-03-18 16:06:52
     * @param string $timezone  时区标识，如 Asia/Shanghai、America/Bogota
     * @return int              UTC 时间戳
     */
    public static function datetimeToTimestamp(string $datetime, string $timezone): int
    {
        $tz = new \DateTimeZone($timezone);
        $dt = new \DateTime($datetime, $tz);
        return $dt->getTimestamp();
    }

    /**
     * 将两个时间戳转固定时区格式化，然后比较天数（按自然日）
     * @param int $time1
     * @param int $time2
     * @param string $inia Asia/Shanghai
     * @return false|int
     * @throws \Exception
     */
    public static function formatDiffTimestamp(int $time1, int $time2, $inia = 'PRC') {
        $date1 = date(self::FULL, self::getTimeZoneStamp($time1, $inia));
        $date2 = date(self::FULL, self::getTimeZoneStamp($time2, $inia));
        $dt1 = new \DateTime($date1);
        $dt2 = new \DateTime($date2);

        // 只比较日期
        $dt1->setTime(0, 0);
        $dt2->setTime(0, 0);

        $diff = $dt1->diff($dt2);
        return $diff->days;
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
