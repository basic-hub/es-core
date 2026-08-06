<?php

namespace BasicHub\EsCore\Common\CloudLib\Geo;

interface GeoInterface
{
    /**
     * 获取ip解析的国家/地区
     * @param string $ip
     * @return array 解析失败返回 Base::FAIL_AREA (['未知'])
     */
    public function getArea($ip);

    /**
     * 获取isp网络供应商
     * @param string $ip
     * @return string 解析失败返回 Base::FAIL_ISP ('未知')
     */
    public function getIsp($ip);

    /**
     * 获取ip解析的国家 alpha-2 代码（ISO 3166-1 二位字母，如 CN、US、TW）
     * @param string $ip
     * @return string 解析失败返回 Iso3166::FAIL_ALPHA2 ('ZZ')
     */
    public function getAlpha2($ip);

    /**
     * 获取ip解析的国家 alpha-3 代码（ISO 3166-1 三位字母，如 CHN、USA、TWN）
     * @param string $ip
     * @return string 解析失败返回 Iso3166::FAIL_ALPHA3 ('ZZZ')
     */
    public function getAlpha3($ip);
}
