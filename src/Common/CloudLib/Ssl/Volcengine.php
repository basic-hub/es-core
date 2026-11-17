<?php

namespace BasicHub\EsCore\Common\CloudLib\Ssl;

use EasySwoole\HttpClient\HttpClient;

/**
 *  火山引擎证书中心（SSL证书）
 *  非核心功能尽量不安装依赖，此处使用原生接口实现（HMAC-SHA256 签名）
 * @document 证书实例列表 CertificateGetInstanceList：https://www.volcengine.com/docs/6638/1413343
 * @document 签名机制：https://www.volcengine.com/docs/6369/67269
 * todo 火山API化还缺少很多接口支持
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
     * @param string $action  接口动作名，如 CertificateGetInstanceList
     * @param array  $body    接口业务参数（JSON Body）
     * @param array  $product 跨产品调用配置，可选 host/service/version/region，缺省走证书中心
     * @return bool|array
     * @throws \Exception
     */
    protected function request($action, array $body, array $product = [])
    {
        // 默认请求证书中心，$product 非空时请求其他云产品（如 CDN、CLB、WAF）
        $host = !empty($product['host']) ? $product['host'] : parse_url($this->endpoint, PHP_URL_HOST);
        $service = !empty($product['service']) ? $product['service'] : $this->service;
        $version = !empty($product['version']) ? $product['version'] : $this->version;
        $region = !empty($product['region']) ? $product['region'] : $this->region;

        $query = 'Action=' . $action . '&Version=' . $version;
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
        $credentialScope = "$shortDate/$region/$service/request";
        $hashedCanonicalRequest = hash('sha256', $canonicalRequest);
        $stringToSign = "HMAC-SHA256\n$xDate\n$credentialScope\n$hashedCanonicalRequest";

// ************* 步骤 3：派生签名密钥（使用原始二进制作为下一轮 HMAC 的密钥，与 AWS V4 一致）*************
        $kDate = hash_hmac('sha256', $shortDate, $this->sk, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
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

        $url = 'https://' . $host . '/?' . $query;

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

    /**
     * 上传证书到火山引擎证书中心
     * @document https://www.volcengine.com/docs/6638/1413338
     * @param string $cert PEM格式证书内容或证书文件路径（含证书链）
     * @param string $key PEM格式私钥内容或私钥文件路径
     * @param array $params 其他参数，如：
     *   - AlgorithmType: 算法类型，1=非国密（RSA/ECC，默认），2=国密 SM2
     *   - Name: 证书名称（可选）
     *   - Description: 证书描述/备注（可选）
     *   - ExpectedCertType: 预期证书类型，1=DV，2=OV，3=EV（可选）
     *   - ProjectName: 项目名称（可选）
     *   - Tags: 标签（可选）
     * @return array Result body，含 InstanceId、Source（3=上传证书）
     * @throws \Exception
     */
    public function upload($cert, $key, $params = [])
    {
        // 解析证书和私钥（支持 PEM 内容或文件路径）
        $certContent = $this->parsePem($cert, '证书');
        $keyContent = $this->parsePem($key, '私钥');

        $requestParams = [
            'AlgorithmType' => isset($params['AlgorithmType']) ? intval($params['AlgorithmType']) : 1,
            'PublicKey' => $certContent,
            'PrivateKey' => $keyContent,
        ];

        // 可选参数
        foreach (['Name', 'Description', 'ProjectName', 'Tags'] as $field) {
            if (isset($params[$field])) {
                $requestParams[$field] = $params[$field];
            }
        }
        if (isset($params['ExpectedCertType'])) {
            $requestParams['ExpectedCertType'] = intval($params['ExpectedCertType']);
        }

        $result = $this->request('ImportCertificate', $requestParams);

        $responseMetadata = $result['ResponseMetadata'] ?? [];
        if (isset($responseMetadata['Error'])) {
            throw new \Exception('Response Error: ' . ($responseMetadata['Error']['Message'] ?? ''));
        }

        return $result['Result'] ?? [];
    }

    /**
     * 查询证书详情
     * @document https://www.volcengine.com/docs/6638/1413341
     * @param string $certificateId 证书实例ID
     * @return array Result body，含 InstanceId/Name/CommonName/NotBefore/NotAfter 等
     * @throws \Exception
     */
    public function detail($certificateId)
    {
        $result = $this->request('CertificateGetInstance', [
            'InstanceId' => $certificateId,
        ]);

        $responseMetadata = $result['ResponseMetadata'] ?? [];
        if (isset($responseMetadata['Error'])) {
            throw new \Exception('Response Error: ' . ($responseMetadata['Error']['Message'] ?? ''));
        }

        return $result['Result'] ?? [];
    }

    /**
     * 部署/更新资源绑定的SSL证书
     * 火山引擎证书中心仅支持直接消费证书 ID 的产品，其他产品需调用方自行通过产品 API 处理
     * @document CDN BatchDeployCert：https://www.volcengine.com/docs/6454/1263334
     * @param string $certificateId 证书实例ID
     * @param array $params 部署参数，如：
     *   - ResourceType: 资源类型（必填），支持 cdn（CDN加速域名）、tos（对象存储自定义域名）
     *   - Domain: 域名（cdn 类型必填，单个域名字符串或域名数组）
     *   - Bucket: 存储桶名称（tos 类型必填）
     *   - Region: 地域（tos 类型必填，如 cn-beijing）
     * @return array 部署结果
     * @throws \Exception
     */
    public function deployToResources($certificateId, $params)
    {
        // 验证必填参数
        if (empty($params['ResourceType'])) {
            throw new \Exception('资源类型（ResourceType）不能为空');
        }

        $resourceType = strtolower($params['ResourceType']);

        switch ($resourceType) {
            case 'cdn':
                return $this->deployCdn($certificateId, $params);

            case 'tos':
                return $this->deployTos($certificateId, $params);

            default:
                throw new \Exception("不支持的资源类型：{$resourceType}。仅支持 cdn、tos。其他产品（如 CLB、WAF）请通过各产品 API 自行部署证书。");
        }
    }

    /**
     * 部署证书到 CDN 加速域名
     * @param string $certificateId 证书实例ID
     * @param array $params 参数，含 Domain（必填，域名字符串或数组）
     * @return array
     * @throws \Exception
     */
    protected function deployCdn($certificateId, $params)
    {
        if (empty($params['Domain'])) {
            throw new \Exception('CDN 部署缺少必填参数：Domain（域名）');
        }

        $domains = is_array($params['Domain']) ? $params['Domain'] : [$params['Domain']];

        $product = [
            'host' => 'cdn.volcengineapi.com',
            'service' => 'cdn',
            'version' => '2021-03-01',
            'region' => 'cn-north-1',
        ];

        $result = $this->request('BatchDeployCert', [
            'CertId' => $certificateId,
            'Domain' => implode(',', $domains),
        ], $product);

        $responseMetadata = $result['ResponseMetadata'] ?? [];
        if (isset($responseMetadata['Error'])) {
            throw new \Exception('CDN 证书部署失败: ' . ($responseMetadata['Error']['Message'] ?? ''));
        }

        return $result['Result'] ?? [];
    }

    /**
     * 部署证书到 TOS 存储桶自定义域名
     * @param string $certificateId 证书实例ID
     * @param array $params 参数，含 Bucket（必填）、Region（必填）、Domain（必填）
     * @return array
     * @throws \Exception
     */
    protected function deployTos($certificateId, $params)
    {
        if (empty($params['Bucket'])) {
            throw new \Exception('TOS 部署缺少必填参数：Bucket（存储桶名称）');
        }
        if (empty($params['Region'])) {
            throw new \Exception('TOS 部署缺少必填参数：Region（地域）');
        }
        if (empty($params['Domain'])) {
            throw new \Exception('TOS 部署缺少必填参数：Domain（自定义域名）');
        }

        $product = [
            'host' => 'tos-' . $params['Region'] . '.volces.com',
            'service' => 'tos',
            'version' => '2021-12-01',
            'region' => $params['Region'],
        ];

        $result = $this->request('PutBucketCustomDomain', [
            'Bucket' => $params['Bucket'],
            'Domain' => $params['Domain'],
            'CertId' => $certificateId,
        ], $product);

        $responseMetadata = $result['ResponseMetadata'] ?? [];
        if (isset($responseMetadata['Error'])) {
            throw new \Exception('TOS 证书部署失败: ' . ($responseMetadata['Error']['Message'] ?? ''));
        }

        return $result['Result'] ?? [];
    }

    /**
     * 查询证书绑定的资源列表
     * 火山引擎证书中心仅支持查询 CDN 产品的证书绑定关系
     * @document https://www.volcengine.com/docs/6454/1263336
     * @param string $certificateId 证书实例ID
     * @param array $params 其他参数（可选，如 PageNum/PageSize）
     * @return array 绑定的资源列表
     * @throws \Exception
     */
    public function listBindings($certificateId, $params = [])
    {
        $product = [
            'host' => 'cdn.volcengineapi.com',
            'service' => 'cdn',
            'version' => '2021-03-01',
            'region' => 'cn-north-1',
        ];

        $requestParams = [
            'CertId' => $certificateId,
        ];

        // 可选分页参数
        if (isset($params['PageNum'])) {
            $requestParams['PageNum'] = intval($params['PageNum']);
        }
        if (isset($params['PageSize'])) {
            $requestParams['PageSize'] = intval($params['PageSize']);
        }

        $result = $this->request('ListCdnCertInfo', $requestParams, $product);

        $responseMetadata = $result['ResponseMetadata'] ?? [];
        if (isset($responseMetadata['Error'])) {
            throw new \Exception('查询证书绑定关系失败: ' . ($responseMetadata['Error']['Message'] ?? ''));
        }

        return $result['Result'] ?? [];
    }
}
