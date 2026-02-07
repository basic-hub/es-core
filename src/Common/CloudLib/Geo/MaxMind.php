<?php

namespace BasicHub\EsCore\Common\CloudLib\Geo;

use GeoIp2\Database\Reader;

/**
 * 由于海外隐私问题，City级别数据库文件可能无法下载，就算下载成功，大部分地区也是无法获取到城市级别数据的，所以只做到国家级
 * @document https://maxmind.github.io/GeoIP2-php/
 * @install composer require geoip2/geoip2:~2.0
 * @download https://www.maxmind.com/en/accounts/1263786/geoip/downloads  asn country
 * @download https://github.com/wp-statistics/GeoLite2-City  city  gunzip -c GeoLite2-City.mmdb.gz > GeoLite2-City.mmdb
 */
class MaxMind extends Base
{
    /**
     * @var mmdb数据库文件路径
     */
    protected $db_file_asn;

    protected $db_file_country;

    protected $db_file_city;

    protected $account_id;

    protected $license_key;

    /**
     * zh-CN|en|de|es|fr|ja|pt-BR|ru
     * @var string
     */
    protected $locales = 'zh-CN';

    protected function convAreaMap($name)
    {
        $maps = array_merge([
            '台湾' => '中国台湾',
            '香港' => '中国香港',
            '澳门' => '中国澳门',
            '中国' => '中国大陆',
        ], $this->area_map);

        return $maps[$name] ?? $name;
    }

    /**
     * @param string $ip
     * @return array
     * @throws \GeoIp2\Exception\AddressNotFoundException
     * @throws \MaxMind\Db\Reader\InvalidDatabaseException
     */
    public function getArea($ip)
    {
        // GeoLite2/GeoIP2 数据库仅收录公网可路由IP的地理信息，内网IP、保留IP、局域网IP等不在数据库覆盖范围内，因此会抛出 \GeoIp2\Exception\AddressNotFoundException
        if ($this->isNonPublicIp($ip)) {
            // 允许外部修改名称
            $name = $this->convAreaMap('NonPublic');
            return [$name];
        }

        try {
            $Reader = new Reader($this->db_file_country, [$this->locales]);

            // 解析IP（支持IPv4/IPv6）
            $Country = $Reader->country($ip);

            $name = $Country->country->name;
            $country = $this->convAreaMap($name);

            $Reader->close();
            return [$country];
        } catch (\Exception $e) {
            if ($Reader instanceof Reader) {
                $Reader->close();
            }
            throw $e;
        }
    }

    public function getIsp($ip)
    {
        try {
            $Reader = new Reader($this->db_file_asn, [$this->locales]);

            // 解析IP（支持IPv4/IPv6）
            // '70.32.128.248'; // GOOGLE
            $name = $Reader->asn($ip)->autonomousSystemOrganization;
            $Reader->close();
            return $name;
        } catch (\Exception $e) {
            if ($Reader instanceof Reader) {
                $Reader->close();
            }
            throw $e;
        }
    }

    /*
     * City级别数据库的示例
     * public function getArea($ip)
    {
        try {
             $Reader = new Reader($this->db_file_city, [$this->locales]);

            // 解析IP（支持IPv4/IPv6）
            $City = $Reader->city($ip);

            $list = array_filter([
                $City->country->name,
                $City->city->name
            ]);

            $str = implode('-', $list);
            var_dump($str, '===str');
            $str = $this->convAreaMap($str);

            $Reader->close();
            return explode('-', $str);
        } catch (\Exception $e) {
            if ($Reader instanceof Reader) {
                $Reader->close();
            }
            trace($e->toString(), 'error');
            throw $e;
        }
    }*/
}
