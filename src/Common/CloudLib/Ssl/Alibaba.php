<?php

namespace BasicHub\EsCore\Common\CloudLib\Ssl;

use EasySwoole\HttpClient\HttpClient;

/**
 *  阿里云数字证书管理服务（原SSL证书）
 *  非核心功能尽量不安装依赖，此处使用原生接口实现（RPC 风格签名 HMAC-SHA1）
 * todo 阿里云未测试
 * @document 证书列表 ListUserCertificateOrder：https://help.aliyun.com/zh/ssl-certificate/developer-reference/api-cas-2020-04-07-listusercertificateorder
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
     * 资源类型
     * CERT=已签发证书，UPLOAD=上传证书，CPACK=证书包订单，BUY=购买订单
     * 默认查询已签发证书，查询上传的证书可传 OrderType=UPLOAD
     * @var string
     */
    protected $orderType = 'CERT';

    /**
     * 列出证书（单页）
     * @param array $params 接口参数（PascalCase），如 Keyword/Status/OrderType/CurrentPage/ShowSize/ResourceGroupId
     * @return array Response body，含 TotalCount、CertificateOrderList、RequestId
     * @throws \Exception
     */
    public function list($params = [])
    {
        // 默认查询已签发证书，调用方显式传入则以调用方为准
        if (!isset($params['OrderType']) && $this->orderType) {
            $params['OrderType'] = $this->orderType;
        }

        $result = $this->request('ListUserCertificateOrder', $params);

        if (isset($result['Code'])) {
            throw new \Exception('Response Error: ' . ($result['Message'] ?? ''));
        }

        return $result;
    }

    /**
     * 列出账号下所有证书（自动分页拉取）
     * @param array $params 接口参数（无需传 CurrentPage/ShowSize，内部自动处理）
     * @return array 全部 CertificateOrderList
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
            $certs = $response['CertificateOrderList'] ?? [];
            $total = $response['TotalCount'] ?? 0;

            $all = array_merge($all, $certs);
            $page++;
        } while (count($all) < $total && !empty($certs));

        // 默认按到期时间升序（快到期的排在最前面）
        usort($all, function ($a, $b) {
            $ta = strtotime($a['EndDate'] ?? '') ?: 0;
            $tb = strtotime($b['EndDate'] ?? '') ?: 0;
            return $ta <=> $tb;
        });

        return $all;
    }

    /**
     * 将证书列表输出为控制台表格（含证书天数、有效期天数）
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
            $begin = $cert['StartDate'] ?? '';
            $end = $cert['EndDate'] ?? '';
            $days = $this->calcCertDays($begin, $end);
            $remain = $this->calcRemainDays($end);
            $result[] = [
                'domain' => $cert['Domain'] ?? ($cert['CommonName'] ?? ''),
                'alias' => $cert['Name'] ?? '',
                'product' => $cert['ProductName'] ?? '',
                'status' => $cert['Status'] ?? '',
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
            'alias' => '备注',
            'product' => '产品名称',
            'status' => '状态',
            'days' => '证书天数',
            'remain' => '剩余天数',
            'begin' => '开始时间',
            'end' => '到期时间',
            'id' => '实例ID',
        ];
        $this->echo($header, $result, $rowColors);
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
     * @param string $action 接口动作名，如 ListUserCertificateOrder
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
