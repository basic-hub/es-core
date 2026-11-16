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
     * 查询证书绑定资源API，超时时间，单位秒
     * @var int
     */
    protected $listBindMaxRetries = 30;

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

    /**
     * 上传证书到腾讯云证书管理服务
     * @document https://cloud.tencent.com/document/product/400/41665
     * @param string $cert PEM格式证书内容或证书文件路径
     * @param string $key PEM格式私钥内容或私钥文件路径
     * @param array $params 其他参数，如：
     *   - Alias: 证书备注名称（可选）
     *   - CertificateType: 证书类型，默认 SVR（服务器证书），可选 CA（客户端证书）
     *   - Repeatable: 是否允许重复上传（true=允许，false=不允许，默认false）
     * @return array 响应数据，含 CertificateId、RepeatCertId（重复上传时返回已存在的证书ID）、RequestId
     * @throws \Exception
     */
    public function upload($cert, $key, $params)
    {
        // 解析证书和私钥（支持 PEM 内容或文件路径）
        $certContent = $this->parsePem($cert, '证书');
        $keyContent = $this->parsePem($key, '私钥');

        // 构造请求参数
        $requestParams = [
            'CertificatePublicKey' => $certContent,
            'CertificatePrivateKey' => $keyContent,
        ];

        // 可选参数
        if (isset($params['Alias'])) {
            $requestParams['Alias'] = $params['Alias'];
        }
        if (isset($params['CertificateType'])) {
            $requestParams['CertificateType'] = $params['CertificateType'];
        } else {
            $requestParams['CertificateType'] = 'SVR'; // 默认服务器证书
        }
        if (isset($params['Repeatable'])) {
            $requestParams['Repeatable'] = $params['Repeatable'];
        }

        $result = $this->request('UploadCertificate', $requestParams);

        $response = $result['Response'] ?? [];
        if (isset($response['Error'])) {
            throw new \Exception('Response Error: ' . ($response['Error']['Message'] ?? ''));
        }

        return $response;
    }

    /**
     * 部署/更新资源绑定的SSL证书（统一部署接口）
     * @document https://cloud.tencent.com/document/product/400/91667
     * @param string $certificateId 证书ID
     * @param array $params 部署参数，如：
     *   - ResourceType: 资源类型（必填），支持 cdn/clb/waf/teo/ddos/live/vod/apigateway/tke/cos/tdmq/mqtt/scf/gaap/tcb 等
     *   - InstanceIdList: 资源实例ID列表（必填，数组），如 ['cdn-xxx', 'cdn-yyy']
     *   - Region: 地域（可选，部分资源类型需要）
     * @return array 响应数据，含 DeployRecordId、DeployStatus、RequestId
     * @throws \Exception
     */
    public function deployToResources($certificateId, $params)
    {
        // 验证必填参数
        if (empty($params['ResourceType'])) {
            throw new \Exception('资源类型（ResourceType）不能为空');
        }
        if (empty($params['InstanceIdList']) || !is_array($params['InstanceIdList'])) {
            throw new \Exception('资源实例ID列表（InstanceIdList）不能为空且必须为数组');
        }

        // 构造请求参数
        $requestParams = [
            'CertificateId' => $certificateId,
            'ResourceType' => $params['ResourceType'],
            'InstanceIdList' => $params['InstanceIdList'],
        ];

        // 可选参数
        if (isset($params['Region'])) {
            $requestParams['Region'] = $params['Region'];
        }

        $result = $this->request('DeployCertificateInstance', $requestParams);

        $response = $result['Response'] ?? [];
        if (isset($response['Error'])) {
            throw new \Exception('Response Error: ' . ($response['Error']['Message'] ?? ''));
        }

        return $response;
    }

    /**
     * 查询证书详情
     * @document https://cloud.tencent.com/document/api/400/41673
     * @param string $certificateId 证书ID
     * @return array 响应数据，含证书详细信息
     * @throws \Exception
     */
    public function detail($certificateId)
    {
        $result = $this->request('DescribeCertificateDetail', ['CertificateId' => $certificateId]);

        $response = $result['Response'] ?? [];
        if (isset($response['Error'])) {
            throw new \Exception('Response Error: ' . ($response['Error']['Message'] ?? ''));
        }

        return $response;
    }

    /**
     * 查询证书绑定的资源列表（异步两步查询）
     * @document https://cloud.tencent.com/document/product/400/97758
     * @param string $certificateId 证书ID
     * @param array $params 可选参数，如：
     *   - ResourceType: 资源类型（可选），支持 clb/cdn/waf/live/vod/ddos/tke/apigateway/tcb/teo/cos/gaap/mqtt/scf/tdmq
     *   - Region: 地域（可选）
     * @return array 绑定的资源列表，含资源类型、实例ID、地域等信息
     * @throws \Exception
     */
    public function listBindings($certificateId, $params = [])
    {
        // 步骤1：创建查询任务
        $taskParams = ['CertificateIds' => [$certificateId]];
        if (!empty($params['ResourceType'])) {
            $taskParams['ResourceType'] = $params['ResourceType'];
        }
        if (!empty($params['Region'])) {
            $taskParams['Region'] = $params['Region'];
        }

        $taskResult = $this->request('CreateCertificateBindResourceSyncTask', $taskParams);
        $taskResponse = $taskResult['Response'] ?? [];
        if (isset($taskResponse['Error'])) {
            throw new \Exception('创建查询任务失败: ' . ($taskResponse['Error']['Message'] ?? ''));
        }

        $taskId = $taskResponse['TaskId'] ?? '';
        if (empty($taskId)) {
            throw new \Exception('创建查询任务失败: TaskId 为空');
        }

        // 步骤2：查询任务结果（轮询最多10次，每次间隔1秒）
        $maxRetries = $this->listBindMaxRetries;
        for ($i = 0; $i < $maxRetries; $i++) {
            if ($i > 0) {
                \Swoole\Coroutine::sleep(1);
            }

            $detailResult = $this->request('DescribeCertificateBindResourceTaskDetail', ['TaskId' => $taskId]);
            $detailResponse = $detailResult['Response'] ?? [];
            if (isset($detailResponse['Error'])) {
                throw new \Exception('查询任务结果失败: ' . ($detailResponse['Error']['Message'] ?? ''));
            }

            $status = $detailResponse['Status'] ?? '';
            if ($status === 'success') {
                return $detailResponse['CertBindResourceDetail'] ?? [];
            } elseif ($status === 'error') {
                throw new \Exception('查询任务失败: ' . ($detailResponse['Error'] ?? '未知错误'));
            }
            // status 为 processing 时继续轮询
        }

        throw new \Exception("查询任务超过${$maxRetries}s，已超时，请稍后重试");
    }
}
