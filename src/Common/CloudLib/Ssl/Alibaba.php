<?php

namespace BasicHub\EsCore\Common\CloudLib\Ssl;

use EasySwoole\HttpClient\HttpClient;

/**
 *  阿里云数字证书管理服务（原SSL证书）
 *  非核心功能尽量不安装依赖，此处使用原生接口实现（RPC 风格签名 HMAC-SHA1）
 * @document 证书列表 ListCertificates：https://help.aliyun.com/zh/ssl-certificate/developer-reference/api-cas-2020-04-07-listcertificates
 * @document 签名机制：https://help.aliyun.com/zh/user-guide/overview-of-rpc-api-signatures
 */
class Alibaba extends Base
{
    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    protected $endpoint = 'https://cas.aliyuncs.com';

    /**
     * 阿里云API版本
     * @var string
     */
    protected $version = '2020-04-07';

    /**
     * 单页拉取条数，接口上限100
     * @var int
     */
    protected $limit = 50;

    /**
     * 列出证书（单页）
     * @param array $params 接口参数（PascalCase），如 Keyword/InstanceId/CertificateStatus/CertificateSource/ResourceGroupId/ShowSize/CurrentPage
     *   - CertificateStatus: issued=已签发，revoked=已吊销，willExpire=即将过期，expired=已过期
     *   - CertificateSource: BUY=正式证书，TEST=测试证书，UPLOAD=上传证书
     * @return array Response body，含 TotalCount、CertificateList、RequestId
     * @throws \Exception
     */
    public function list($params = [])
    {
        $result = $this->request('ListCertificates', $params);

        if (isset($result['Code'])) {
            throw new \Exception('Response Error: ' . ($result['Message'] ?? ''));
        }

        return $result;
    }

    /**
     * 列出账号下所有证书（自动分页拉取）
     * @param array $params 接口参数（无需传 CurrentPage/ShowSize，内部自动处理）
     * @return array 全部 CertificateList
     * @throws \Exception
     */
    public function listAll($params = [])
    {
        $limit = !empty($params['ShowSize']) ? min(intval($params['ShowSize']), 100) : $this->limit;
        $page = 1;
        $all = [];

        do {
            $params['ShowSize'] = $limit;
            $params['CurrentPage'] = $page;

            $response = $this->list($params);
            $certs = $response['CertificateList'] ?? [];
            $total = $response['TotalCount'] ?? 0;

            $all = array_merge($all, $certs);
            $page++;
        } while (count($all) < $total && !empty($certs));

        // 默认按到期时间升序（快到期的排在最前面）
        usort($all, function ($a, $b) {
            $ta = intval($a['NotAfter'] ?? 0);
            $tb = intval($b['NotAfter'] ?? 0);
            return $ta <=> $tb;
        });

        return $all;
    }

    /**
     * 将证书列表输出为控制台表格（含证书天数、有效期天数、关联云产品数量）
     * @param array $params 接口参数，同 listAll
     * @return void
     * @throws \Exception
     */
    public function echoTable($params = [])
    {
        $certificates = $this->listAll($params);

        $result = [];
        $rowColors = [];
        foreach ($certificates as $cert) {
            // 阿里云时间为毫秒时间戳，转为日期字符串
            $begin = $this->msToDate($cert['NotBefore'] ?? 0);
            $end = $this->msToDate($cert['NotAfter'] ?? 0);
            $days = $this->calcCertDays($begin, $end);
            $remain = $this->calcRemainDays($end);
            // 关联云产品数量
            $products = $cert['UsingProductList'] ?? [];
            $productCount = is_array($products) ? count($products) : 0;
            $result[] = [
                'domain' => $cert['CommonName'] ?? '',
                'name' => $cert['CertificateName'] ?? '',
                'issuer' => $cert['Issuer'] ?? '',
                'source' => $this->sourceMap($cert['CertificateSource'] ?? ''),
                'status' => $this->statusMap($cert['CertificateStatus'] ?? ''),
                'products' => $productCount,
                'days' => $days,
                'remain' => $remain,
                'begin' => $begin,
                'end' => $end,
                'id' => $cert['InstanceId'] ?? '',
            ];
            $rowColors[] = $this->rowColor($remain);
        }

        $header = [
            'domain' => '域名',
            'name' => '证书名称',
            'issuer' => '签发机构',
            'source' => '来源',
            'status' => '状态',
            'products' => '关联云产品数',
            'days' => '证书天数',
            'remain' => '剩余天数',
            'begin' => '开始时间',
            'end' => '到期时间',
            'id' => '实例ID',
        ];
        $this->echo($header, $result, $rowColors);
    }

    /**
     * 毫秒时间戳转日期字符串
     * @param int $ms 毫秒时间戳
     * @return string Y-m-d H:i:s 格式，为0返回空字符串
     */
    protected function msToDate($ms)
    {
        $ms = intval($ms);
        if ($ms <= 0) {
            return '';
        }
        return date('Y-m-d H:i:s', intval($ms / 1000));
    }

    /**
     * 阿里云证书状态枚举转中文
     * @param string $status
     * @return string
     */
    protected function statusMap($status)
    {
        $map = [
            'issued'     => '已签发',
            'revoked'    => '已吊销',
            'willExpire' => '即将过期',
            'expired'    => '已过期',
        ];
        return isset($map[$status]) ? $map[$status] : $status;
    }

    /**
     * 阿里云证书来源枚举转中文
     * @param string $source
     * @return string
     */
    protected function sourceMap($source)
    {
        $map = [
            'BUY'    => '正式证书',
            'TEST'   => '测试证书',
            'UPLOAD' => '上传证书',
        ];
        return isset($map[$source]) ? $map[$source] : $source;
    }

    /**
     * RFC3986 规范的 URL 编码（阿里云 RPC 签名专用）
     * @param string $value
     * @return string
     */
    protected function percentEncode($value)
    {
        $encoded = urlencode($value);
        $encoded = str_replace(['+', '*'], ['%20', '%2A'], $encoded);
        $encoded = str_replace('%7E', '~', $encoded);
        return $encoded;
    }

    /**
     * @param string $action 接口动作名，如 ListCertificates
     * @param array  $params 接口业务参数
     * @return bool|array
     * @throws \Exception
     */
    protected function request($action, array $params)
    {
        $host = 'cas.aliyuncs.com';

        // 公共参数
        $publicParams = [
            'Format' => 'JSON',
            'Version' => $this->version,
            'AccessKeyId' => $this->accessKeyId,
            'SignatureMethod' => 'HMAC-SHA1',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => '1.0',
            'SignatureNonce' => md5(uniqid(mt_rand(), true)),
            'Action' => $action,
        ];

        $allParams = array_merge($publicParams, $params);

        // 参数按字典序升序排序
        ksort($allParams);

        // 拼接规范化的查询字符串
        $canonicalized = '';
        foreach ($allParams as $k => $v) {
            $canonicalized .= '&' . $this->percentEncode($k) . '=' . $this->percentEncode($v);
        }
        $canonicalized = substr($canonicalized, 1);

        // 拼接待签名字符串
        $stringToSign = 'GET&' . $this->percentEncode('/') . '&' . $this->percentEncode($canonicalized);

        // 计算签名
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret . '&', true));
        $allParams['Signature'] = $signature;

        // 构造请求 URL
        $query = '';
        foreach ($allParams as $k => $v) {
            $query .= '&' . $this->percentEncode($k) . '=' . $this->percentEncode($v);
        }
        $query = substr($query, 1);

        $url = $this->endpoint . '/?' . $query;

        try {
            $HttpClient = new HttpClient($url);
            $Resp = $HttpClient->get();
            return $Resp->json(true);
        } catch (\Exception $err) {
            echo $err->getMessage();
            return false;
        }
    }
}
