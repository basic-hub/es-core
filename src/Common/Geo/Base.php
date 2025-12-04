<?php

namespace BasicHub\EsCore\Common\Geo;

use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements GeoInterface
{
    /**
     * 判断IP是否为局域网/IANA保留/非公网IP
     * @param string $ip 待检测IP地址（IPv4/IPv6）
     * @return bool true=非公网IP，false=公网IP
     */
    public function isNonPublicIp(string $ip): bool
    {
        // 所有非公网IP的CIDR段（IPv4+IPv6）
        $nonPublicCidrs = [
            // === IPv4 非公网段 ===
            '0.0.0.0/8',          // 本网络地址
            '10.0.0.0/8',         // RFC1918私有IP
            '127.0.0.0/8',        // 回环地址
            '169.254.0.0/16',     // 链路本地地址（APIPA）
            '172.16.0.0/12',      // RFC1918私有IP
            '192.0.0.0/24',       // IETF协议分配保留
            '192.0.2.0/24',       // TEST-NET-1（文档示例IP）
            '192.88.99.0/24',     // 6to4中继地址
            '192.168.0.0/16',     // RFC1918私有IP
            '198.18.0.0/15',      // 网络基准测试地址
            '198.51.100.0/24',    // TEST-NET-2（文档示例IP）
            '203.0.113.0/24',     // TEST-NET-3（文档示例IP）
            '224.0.0.0/4',        // 组播地址
            '240.0.0.0/4',        // 保留地址（含广播地址）
            '255.255.255.255/32', // 广播地址
            // === IPv6 非公网段 ===
            '::/128',             // 未指定地址
            '::1/128',            // 回环地址
            '::ffff:0:0/96',      // IPv4映射地址
            '100::/64',           // 丢弃前缀地址
            '2001::/32',          // TEREDO隧道地址
            '2001:10::/28',       // ORCHIDv2地址
            '2001:db8::/32',      // 文档示例IP
            '2002::/16',          // 6to4隧道地址
            'fc00::/7',           // ULA私有地址
            'fe80::/10',          // 链路本地地址
            'ff00::/8'            // 组播地址
        ];

        // 第一步：验证IP格式有效性
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true; // 无效IP直接判定为非公网
        }

        // 第二步：将IP转换为二进制（便于CIDR匹配）
        $ipBinary = inet_pton($ip);
        if ($ipBinary === false) {
            return true;
        }

        // 第三步：遍历CIDR段进行匹配
        foreach ($nonPublicCidrs as $cidr) {
            list($network, $mask) = explode('/', $cidr);
            $networkBinary = inet_pton($network);
            if ($networkBinary === false) {
                continue; // 无效网段跳过
            }

            $maskInt = (int)$mask;
            $byteMatchLen = (int)($maskInt / 8); // 需要完整匹配的字节数
            $bitRemain = $maskInt % 8;           // 剩余需要匹配的位数

            // 匹配完整字节部分
            if (strncmp($ipBinary, $networkBinary, $byteMatchLen) !== 0) {
                continue;
            }

            // 匹配剩余位（若有）
            if ($bitRemain === 0) {
                return true; // 掩码刚好是字节整数倍，匹配成功
            }
            $ipByte = ord($ipBinary[$byteMatchLen]);
            $networkByte = ord($networkBinary[$byteMatchLen]);
            $maskByte = (0xff << (8 - $bitRemain)) & 0xff; // 计算剩余位掩码
            if (($ipByte & $maskByte) === ($networkByte & $maskByte)) {
                return true;
            }
        }

        return false;
    }
}
