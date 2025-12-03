<?php

namespace BasicHub\EsCore\Common\Geo;

interface GeoInterface
{
    /**
     * 获取ip解析的国家/地区
     * @param string $ip
     * @return array
     */
    public function getArea($ip);

    /**
     * 获取isp网络供应商
     * @param string $ip
     * @return string
     */
    public function getIsp($ip);
}
