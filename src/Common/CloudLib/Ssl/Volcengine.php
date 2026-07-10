<?php

namespace BasicHub\EsCore\Common\CloudLib\Ssl;

use EasySwoole\HttpClient\HttpClient;

/**
 *  火山引擎证书中心（SSL证书）
 *  非核心功能尽量不安装依赖，此处使用原生接口实现（HMAC-SHA256 签名）
 * @document 证书实例列表 CertificateGetInstanceList：https://www.volcengine.com/docs/6638/1413343
 * @document 签名机制：https://www.volcengine.com/docs/6369/67269
 */
class Volcengine extends Base
{
    protected $ak = '';

    protected $sk = '';

    protected $endpoint = 'https://certificate-service.volcengineapi.com';

    /**
     * 火山引擎API版本
     * @var string
     */
    protected $version = '2024-10-01';

    /**
     * 服务名（用于签名 CredentialScope）
     * @var string
     */
    protected $service = 'certificate_service';

    /**
     * 地域（证书中心固定 cn-beijing）
     * @var string
     */
    protected $region = 'cn-beijing';

    /**
     * 单页拉取条数，接口上限100
     * @var int
     */
    protected $limit = 100;

    /**
     * 列出证书（单页）
     * @param array $params 接口参数（PascalCase），如 InstanceIds/Status/Tag/CommonName/Domain/InstanceType/IsRevoked/IsValid/CertificateExpireBefore/CertificateExpireAfter/PageSize/PageNumber/ProjectName/TagFilters
     * @return array Result body，含 TotalCount、Instances、PageNumber、PageSize
     * @throws \Exception
     */
    public function list($params = [])
    {
        $result = $this->request('CertificateGetInstanceList', $params);

        $responseMetadata = $result['ResponseMetadata'] ?? [];
        if (isset($responseMetadata['Error'])) {
            throw new \Exception('Response Error: ' . ($responseMetadata['Error']['Message'] ?? ''));
        }

        return $result['Result'] ?? [];
    }

    /**
     * 列出账号下所有证书（自动分页拉取）
     * @param array $params 接口参数（无需传 PageNumber/PageSize，内部自动处理）
     * @return array 全部 Instances
     * @throws \Exception
     */
    public function listAll($params = [])
    {
        $limit = !empty($params['PageSize']) ? min(intval($params['PageSize']), 100) : $this->limit;
        $page = 1;
        $all = [];

        do {
            $params['PageSize'] = $limit;
            $params['PageNumber'] = $page;

            $response = $this->list($params);
            $certs = $response['Instances'] ?? [];
            $total = $response['TotalCount'] ?? 0;

            $all = array_merge($all, $certs);
            $page++;
        } while (count($all) < $total && !empty($certs));

        // 默认按到期时间升序（快到期的排在最前面）
        usort($all, function ($a, $b) {
            $ta = strtotime($a['NotAfter'] ?? '') ?: 0;
            $tb = strtotime($b['NotAfter'] ?? '') ?: 0;
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
            $begin = $cert['NotBefore'] ?? '';
            $end = $cert['NotAfter'] ?? '';
            $days = $this->calcCertDays($begin, $end);
            $remain = $this->calcRemainDays($end);
            $result[] = [
                'domain' => $cert['CommonName'] ?? '',
                'alias' => $cert['Tag'] ?? '',
                'product' => $cert['OrderBrand'] ?: ($cert['InstanceType'] ?? ''),
                'status' => $this->statusMap($cert['Status'] ?? ''),
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
     * @param string $action 接口动作名，如 CertificateGetInstanceList
     * @param array  $body   接口业务参数（JSON Body）
     * @return bool|array
     * @throws \Exception
     */
    protected function request($action, array $body)
    {
        $host = 'certificate-service.volcengineapi.com';

        $query = 'Action=' . $action . '&Version=' . $this->version;
        $payload = json_encode($body);

        // X-Date：ISO 8601 UTC，格式 YYYYMMDDTHHMMSSZ
        $xDate = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');

// ************* 步骤 1：拼接规范请求串 *************
        $hashedPayload = hash('sha256', $payload);
        $canonicalHeaders = "host:$host\nx-content-sha256:$hashedPayload\nx-date:$xDate\n";
        $signedHeaders = 'host;x-content-sha256;x-date';
        $canonicalRequest = "POST\n/\n$query\n$canonicalHeaders\n$signedHeaders\n$hashedPayload";

// ************* 步骤 2：拼接待签名字符串 *************
        $credentialScope = "$shortDate/$this->region/$this->service/request";
        $hashedCanonicalRequest = hash('sha256', $canonicalRequest);
        $stringToSign = "HMAC-SHA256\n$xDate\n$credentialScope\n$hashedCanonicalRequest";

// ************* 步骤 3：派生签名密钥（使用原始二进制作为下一轮 HMAC 的密钥，与 AWS V4 一致）*************
        $kDate = hash_hmac('sha256', $shortDate, $this->sk, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $this->service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'request', $kService, true);

// ************* 步骤 4：计算签名 *************
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

// ************* 步骤 5：拼接 Authorization *************
        $authorization = "HMAC-SHA256 Credential={$this->ak}/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

// ************* 步骤 6：构造并发起请求 *************
        $headers = [
            'Authorization' => $authorization,
            'Content-Type' => 'application/json',
            'Host' => $host,
            'X-Date' => $xDate,
            'X-Content-Sha256' => $hashedPayload,
        ];

        $url = $this->endpoint . '/?' . $query;

        try {
            $HttpClient = new HttpClient($url);
            $Resp = $HttpClient->setHeaders($headers, false, false)->post($payload);
            return $Resp->json(true);
        } catch (\Exception $err) {
            echo $err->getMessage();
            return false;
        }
    }

    /**
     * 火山云证书状态枚举转中文
     * @param string $status
     * @return string
     */
    protected function statusMap($status)
    {
        $map = [
            'NotSubmitted' => '待提交申请',
            'Pending'      => '验证中',
            'Issued'       => '已签发',
            'Cancelling'   => '取消中',
            'Canceled'     => '已取消',
            'Revoking'     => '吊销中',
            'Revoked'      => '已吊销',
            'Failed'       => '申请失败',
            'Unknown'      => '未知',
        ];
        return isset($map[$status]) ? $map[$status] : $status;
    }
}
