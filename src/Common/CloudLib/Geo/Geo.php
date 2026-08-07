<?php

namespace BasicHub\EsCore\Common\CloudLib\Geo;

/**
 * IP 地理位置解析入口
 *
 * 封装驱动实例化、方法分发、异常兜底，以及 ISO 3166-1 代码转换。
 * 每个操作对应一个独立静态方法，替代 geo() 函数中的 switch 分发。
 *
 * 用法：
 *   Geo::alpha2($ip)           // 解析IP获取 alpha-2 代码
 *   Geo::alpha3ToAlpha2('CHN') // alpha-3 转 alpha-2
 *   Geo::alpha2ToCn('CN')      // alpha-2 转中文
 */
class Geo
{
    /**
     * 获取驱动实例（geo($ip, 'class') 的等价方法）
     * @param array $config 额外配置项
     * @return GeoInterface
     */
    public static function driver(array $config = [])
    {
        $driver = config('GEO.driver');
        if (is_array($driver)) {
            $config['driver'] = $driver[0] ?? null;
        }
        return get_drivers('geo', 'GEO', $config);
    }

    /**
     * 获取ip解析的国家/地区（geo($ip, 'all') 的等价方法）
     * @param string $ip
     * @param array $config
     * @return array 局域网/私有IP 返回 Base::PRIVATE_AREA (['局域网'])
     *               解析失败/真正未知 返回 Base::FAIL_AREA (['未知'])
     */
    public static function area($ip, array $config = [])
    {
        try {
            return self::driver($config)->getArea($ip);
        } catch (\Exception|\Throwable $e) {
            trace($e->__toString(), 'info', 'geo');
            return Base::FAIL_AREA;
        }
    }

    /**
     * 获取isp网络供应商（geo($ip, 'isp') 的等价方法）
     * @param string $ip
     * @param array $config
     * @return string 局域网/私有IP 返回 Base::PRIVATE_ISP ('局域网')
     *                解析失败/真正未知 返回 Base::FAIL_ISP ('未知')
     */
    public static function isp($ip, array $config = [])
    {
        try {
            return self::driver($config)->getIsp($ip);
        } catch (\Exception|\Throwable $e) {
            trace($e->__toString(), 'info', 'geo');
            return Base::FAIL_ISP;
        }
    }

    /**
     * 获取国家 alpha-2 代码（geo($ip, 'alpha2') 的等价方法）
     * @param string $ip
     * @param array $config
     * @return string 解析失败返回 Iso3166::FAIL_ALPHA2 ('ZZ')
     */
    public static function alpha2($ip, array $config = [])
    {
        try {
            return self::driver($config)->getAlpha2($ip);
        } catch (\Exception|\Throwable $e) {
            trace($e->__toString(), 'info', 'geo');
            return Iso3166::FAIL_ALPHA2;
        }
    }

    /**
     * 获取国家 alpha-3 代码（geo($ip, 'alpha3') 的等价方法）
     * @param string $ip
     * @param array $config
     * @return string 解析失败返回 Iso3166::FAIL_ALPHA3 ('ZZZ')
     */
    public static function alpha3($ip, array $config = [])
    {
        try {
            return self::driver($config)->getAlpha3($ip);
        } catch (\Exception|\Throwable $e) {
            trace($e->__toString(), 'info', 'geo');
            return Iso3166::FAIL_ALPHA3;
        }
    }

    /**
     * 获取ip解析地址中的指定索引成员（geo($ip, 0) 的等价方法）
     * @param string $ip
     * @param int $num 索引
     * @param array $config
     * @return string|null 解析失败返回 Base::FAIL_AREA[0] ('未知')
     */
    public static function index($ip, $num = 0, array $config = [])
    {
        try {
            return self::driver($config)->getArea($ip)[$num] ?? null;
        } catch (\Exception|\Throwable $e) {
            trace($e->__toString(), 'info', 'geo');
            return Base::FAIL_AREA[0];
        }
    }

    // ==================== ISO 3166-1 代码转换 ====================

    /**
     * alpha-3 转 alpha-2
     * @param string $alpha3 三位字母代码（如 CHN），大小写不敏感
     * @return string|null 二位字母代码（如 CN），未找到返回 null
     */
    public static function alpha3ToAlpha2($alpha3)
    {
        return Iso3166::alpha3ToAlpha2($alpha3);
    }

    /**
     * alpha-2 转 alpha-3
     * @param string $alpha2 二位字母代码（如 CN），大小写不敏感
     * @return string|null 三位字母代码（如 CHN），未找到返回 null
     */
    public static function alpha2ToAlpha3($alpha2)
    {
        return Iso3166::alpha2ToAlpha3($alpha2);
    }

    /**
     * alpha-2 转中文
     * @param string $alpha2 二位字母代码（如 CN），大小写不敏感
     * @param array $override alpha-2 => 中文名 覆盖映射，例如 ['CN' => '中国']
     * @return string|null 中文名，未找到返回 null
     */
    public static function alpha2ToCn($alpha2, array $override = [])
    {
        return Iso3166::alpha2ToCn($alpha2, $override);
    }

    /**
     * alpha-3 转中文
     * @param string $alpha3 三位字母代码（如 CHN），大小写不敏感
     * @param array $override alpha-3 => 中文名 覆盖映射，例如 ['CHN' => '中国']
     * @return string|null 中文名，未找到返回 null
     */
    public static function alpha3ToCn($alpha3, array $override = [])
    {
        return Iso3166::alpha3ToCn($alpha3, $override);
    }
}
