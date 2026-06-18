<?php


namespace BasicHub\EsCore\Common\Classes;

/**
 * @document timezones list  https://www.php.net/manual/zh/timezones.php
 */
class DateUtils
{
    const YMD = 'ymd';
    const FULL = 'Y-m-d H:i:s';
    const FULL_START = 'Y-m-d 00:00:00';
    const FULL_END = 'Y-m-d 23:59:59';
    const YmdHis = 'YmdHis';
    const FMT_1 = 'Y-m-d';

    // 常用UTC时区
    const UTC0 = '+0:00';
    const UTC1 = '+1:00';
    const UTC2 = '+2:00';
    const UTC3 = '+3:00';
    const UTC8 = '+8:00';
    const UTC9 = '+9:00';
    // 下划线表示负数
    const UTC_4 = '-4:00';
    const UTC_5 = '-5:00';
    const UTC_7 = '-7:00';
    const UTC_8 = '-8:00';

    // Asia/Shanghai的别名，不推荐，兼容性差，且未来版本可能废弃
    const PRC = 'PRC';
    // 上海（UTC+8）
    const ASIA_SHANGHAI = 'Asia/Shanghai';
    // 香港（UTC+8）
    const ASIA_HONGKONG = 'Asia/Hong_Kong';
    // 新加坡（UTC+8）
    const ASIA_SINGAPORE = 'Asia/Singapore';
    // 日本-东京（UTC+9）
    const ASIA_TOKYO = 'Asia/Tokyo';
    // 韩国-首尔 （UTC+9）
    const ASIA_SEOUL = 'Asia/Seoul';
    // 美国东部，纽约-含弗吉尼亚（标准：UTC-5，夏令时：UTC-4）
    const AMERICA_NEWYORK = 'America/New_York';
    // 美国西部，洛杉矶-含加州、旧金山、硅谷（标准：UTC-8，夏令时：UTC-7）
    const AMERICA_LOSANGELES = 'America/Los_Angeles';
    // 哥伦比亚波哥大,全年固定采用UTC-5
    const AMERICA_BOGOTA = 'America/Bogota';
    // 德国-法兰克福 （标准：UTC+1，夏令时：UTC+2）
    const EUROPE_FRANKFURT = 'Europe/Frankfurt';
    // 土耳其-伊斯坦布尔  固定UTC+3
    const EUROPE_ISTANBUL = 'Europe/Istanbul';
    // 俄罗斯-莫斯科、圣彼得堡，固定 UTC+3（俄罗斯实际跨十几个时区，以莫斯科为准即可）
    const EUROPE_MOSCOW = 'Europe/Moscow';

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
    public static function timestampToDatetime(int $timestamp, $inia = self::ASIA_SHANGHAI, $format = self::FULL): string
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
     * 获取时区的 UTC 偏移字符串，格式如 UTC+8:00、UTC-5:30
     * @param string|null $inia 时区标识，如 Asia/Shanghai、America/New_York，为 null 时取服务器当前时区
     * @return string
     */
    public static function getTimeZoneUTC(string $inia = null): string
    {
        $inia = $inia !== null ? $inia : date_default_timezone_get();
        $offset = (int)(new \DateTimeZone($inia))
            ->getOffset(new \DateTime('now', new \DateTimeZone('UTC')));

        $sign = $offset >= 0 ? '+' : '-';
        $abs = abs($offset);
        $hours = intdiv($abs, 3600);
        $minutes = intdiv($abs % 3600, 60);

        return sprintf('UTC%s%d:%02d', $sign, $hours, $minutes);
    }

    /**
     * 将时区标识转换为 UTC 偏移小时数
     * @param string|null $inia 时区标识，如 Asia/Shanghai、America/New_York，为 null 时取服务器当前时区
     * @return int 如 8、-5
     */
    public static function getTimezoneOffset(string $inia = null): int
    {
        $inia = $inia !== null ? $inia : date_default_timezone_get();
        $offset = (int)(new \DateTimeZone($inia))
            ->getOffset(new \DateTime('now', new \DateTimeZone('UTC')));

        return intdiv($offset, 3600);
    }

    /**
     * 将两个时间戳转固定时区格式化，然后比较天数（按自然日）
     * @param int $time1
     * @param int $time2
     * @param string $inia Asia/Shanghai
     * @return int
     */
    public static function formatDiffTimestamp(int $time1, int $time2, $inia = self::ASIA_SHANGHAI): int
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
