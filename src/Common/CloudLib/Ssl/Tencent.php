<?php

namespace BasicHub\EsCore\Common\CloudLib\Ssl;

use EasySwoole\HttpClient\HttpClient;

/**
 *  composer require TencentCloud/Ssl
 *  非核心功能尽量不安装依赖，此处使用原生接口实现
 * @document 证书列表 DescribeCertificates：https://cloud.tencent.com/document/product/400/41671
 * @document 响应字段 Certificates：https://cloud.tencent.com/document/api/400/41679#Certificates
 */
class Tencent extends Base
{
    protected $secretId = '';

    protected $secretKey = '';

    protected $endpoint = 'https://ssl.tencentcloudapi.com';

    /**
     * 腾讯SSL接口不需要传递Region
     * @document https://cloud.tencent.com/document/product/400/41671
     * @var string
     */
    protected $region = '';

    /**
     * 腾讯SDK版本
     * @var string
     */
    protected $version = '2019-12-05';

    protected $token = '';

    /**
     * 单页拉取条数，接口上限1000
     * @var int
     */
    protected $limit = 100;

    /**
     * 到期时间排序，默认快到期的排在最前面
     * @document https://cloud.tencent.com/document/product/400/41671
     *           DESC = 证书到期时间降序（最晚到期的在前）
     *           ASC  = 证书到期时间升序（最快到期的在前，即快到期的排最前）
     * @var string
     */
    protected $expirationSort = 'ASC';

    /**
     * 列出证书（单页）
     * @param array $params 接口参数（PascalCase），如 Offset/Limit/SearchKey/CertificateType/CertificateStatus/ExpirationSort/Deployable/Upload/Renew/FilterSource/IsSM/FilterExpiring/Hostable/Tags/IsPendingIssue/CertIds/ServiceId
     * @return array Response body，含 TotalCount、Certificates、RequestId
     * @throws \Exception
     */
    public function list($params = [])
    {
        // 默认按到期时间升序（快到期的排在最前面），调用方显式传入则以调用方为准
        if (!isset($params['ExpirationSort']) && $this->expirationSort) {
            $params['ExpirationSort'] = $this->expirationSort;
        }

        $result = $this->request('DescribeCertificates', $params);

        $response = $result['Response'] ?? [];
        if (empty($response)) {
            throw new \Exception('Response is Empty.');
        }
        if (isset($response['Error'])) {
            throw new \Exception('Response Error: ' . ($response['Error']['Message'] ?? ''));
        }

        return $response;
    }

    /**
     * 列出账号下所有证书（自动分页拉取）
     * @param array $params 接口参数（无需传 Offset/Limit，内部自动处理）
     * @return array 全部 Certificates
     * @throws \Exception
     */
    public function listAll($params = [])
    {
        $limit = !empty($params['Limit']) ? min(intval($params['Limit']), 1000) : $this->limit;
        $offset = 0;
        $all = [];

        do {
            $params['Limit'] = $limit;
            $params['Offset'] = $offset;

            $response = $this->list($params);
            $certs = $response['Certificates'] ?? [];
            $total = $response['TotalCount'] ?? 0;

            $all = array_merge($all, $certs);
            $offset += $limit;
        } while ($offset < $total && !empty($certs));

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
            $days = $this->calcCertDays($cert['CertBeginTime'] ?? '', $cert['CertEndTime'] ?? '');
            $remain = $this->calcRemainDays($cert['CertEndTime'] ?? '');
            $result[] = [
                'domain' => $cert['Domain'] ?? '',
                'alias' => $cert['Alias'] ?? '',
                'product' => $cert['ProductZhName'] ?? '',
                'status' => $cert['StatusName'] ?? '',
                'days' => $days,
                'remain' => $remain,
                'begin' => $cert['CertBeginTime'] ?? '',
                'end' => $cert['CertEndTime'] ?? '',
                'id' => $cert['CertificateId'] ?? '',
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
            'id' => '证书ID',
        ];
        $this->echo($header, $result, $rowColors);
    }

    protected function sign($key, $msg)
    {
        return hash_hmac('sha256', $msg, $key, true);
    }

    /**
     * @param string $action  接口动作名，如 DescribeCertificates
     * @param array  $params  接口业务参数
     * @return bool|array
     * @throws \Exception
     */
    protected function request($action, array $params)
    {
        $service = 'ssl';
        $host = 'ssl.tencentcloudapi.com';

        $payload = json_encode($params);
        $algorithm = 'TC3-HMAC-SHA256';
        $timestamp = time();
        $date = gmdate('Y-m-d', $timestamp);

// ************* 步骤 1：拼接规范请求串 *************
        $http_request_method = 'POST';
        $canonical_uri = '/';
        $canonical_querystring = '';
        $ct = 'application/json; charset=utf-8';
        $canonical_headers = "content-type:".$ct."\nhost:".$host."\nx-tc-action:".strtolower($action)."\n";
        $signed_headers = 'content-type;host;x-tc-action';
        $hashed_request_payload = hash('sha256', $payload);
        $canonical_request = "$http_request_method\n$canonical_uri\n$canonical_querystring\n$canonical_headers\n$signed_headers\n$hashed_request_payload";

// ************* 步骤 2：拼接待签名字符串 *************
        $credential_scope = "$date/$service/tc3_request";
        $hashed_canonical_request = hash("sha256", $canonical_request);
        $string_to_sign = "$algorithm\n$timestamp\n$credential_scope\n$hashed_canonical_request";

// ************* 步骤 3：计算签名 *************
        $secret_date = $this->sign('TC3' . $this->secretKey, $date);
        $secret_service = $this->sign($secret_date, $service);
        $secret_signing = $this->sign($secret_service, 'tc3_request');
        $signature = hash_hmac('sha256', $string_to_sign, $secret_signing);

// ************* 步骤 4：拼接 Authorization *************
        $authorization = "$algorithm Credential={$this->secretId}/$credential_scope, SignedHeaders=$signed_headers, Signature=$signature";

// ************* 步骤 5：构造并发起请求 *************
        $headers = [
            'Authorization' => $authorization,
            'Content-Type' => "application/json; charset=utf-8",
            'Host' => $host,
            'X-TC-Action' => $action,
            'X-TC-Timestamp' => $timestamp,
            'X-TC-Version' => $this->version
        ];
        if ($this->region) {
            $headers['X-TC-Region'] = $this->region;
        }
        if ($this->token) {
            $headers['X-TC-Token'] = $this->token;
        }

        try {
            $HttpClient = new HttpClient($this->endpoint);
            $Resp = $HttpClient->setHeaders($headers, false, false)->post($payload);
            return $Resp->json(true);
        } catch (\Exception $err) {
            echo $err->getMessage();
            return false;
        }
    }
}
