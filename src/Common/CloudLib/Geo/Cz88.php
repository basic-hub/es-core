<?php

namespace BasicHub\EsCore\Common\CloudLib\Geo;

use Czdb\DbSearcher;

/**
 * 纯真IP，离线库，适用国内环境
 * @install composer require czdb/searcher
 * @github https://github.com/tagphi/czdb_searcher_php
 * @website https://cz88.net/
 */
class Cz88 extends Base
{
    /**
     * ipv4数据库文件路径
     * @var string
     */
    protected $db_file_ipv4;

    /**
     * ipv6数据库文件路径
     * @var string
     */
    protected $db_file_ipv6;

    /**
     * 密钥
     * @var string
     */
    protected $key;

    /**
     * 查询模式 , BTREE(默认) | MEMORY，见DbSearcher类常量
     * @var string
     */
    protected $query_type = DbSearcher::QUERY_TYPE_BTREE;

    // 有-标识，模糊匹配
    protected function convAreaMap($name)
    {
        $maps = array_merge([
            '中国–台湾' => '中国台湾–台湾',
            '中国–香港' => '中国香港–香港',
            '中国–澳门' => '中国澳门–澳门',
            // 这个key一定要在最后
            '中国–' => '中国大陆–',
        ], $this->area_map);

        foreach ($maps as $key => $val) {
            $name = str_replace($key, $val, $name);
        }

        return $name;
    }

    public function getArea($ip)
    {
        $arr = $this->search($ip);
        $str = $this->convAreaMap($arr[0]);
        return explode('–', $str);
    }

    public function getIsp($ip)
    {
        $arr = $this->search($ip);
        return $arr[1] ?? '';
    }

    protected function search($ip)
    {
        try {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                // ipv6
                $dbFile = $this->db_file_ipv6;
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // ipv4
                $dbFile = $this->db_file_ipv4;
            } else {
                throw new \Exception("ip invalid: $ip");
            }

            if (!file_exists($dbFile)) {
                throw new \Exception("ip file not exists: $dbFile");
            }

            $dbSearcher = new DbSearcher($dbFile, $this->query_type, $this->key);
            $region = $dbSearcher->search($ip);
            $dbSearcher->close();

            // ip解析示例：
            // ["美国–新泽西州–伯灵顿", "Comcast有线通信股份有限公司"]
            // ["美国–加利福尼亚州–圣克拉拉–山景城", "谷歌公司"]  70.32.128.248
            // ["中国–广东–深圳", "电信"]
            // ["中国–台湾", "中华电信]
            // ["中国–香港", "城市电讯有限公司"]
            // ["中国–澳门", "澳门电讯"]

            return explode("\t", $region);
        } catch (\Exception|\Throwable $e) {
            if ($dbSearcher instanceof DbSearcher) {
                $dbSearcher->close();
            }
            throw $e;
        }
    }
}
