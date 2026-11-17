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
     * 上传证书到阿里云证书管理服务
     * @document https://help.aliyun.com/zh/ssl-certificate/developer-reference/api-cas-2020-04-07-uploadusercertificate
     * @param string $cert PEM格式证书内容或证书文件路径
     * @param string $key PEM格式私钥内容或私钥文件路径
     * @param array $params 其他参数，如：
     *   - Name: 证书名称（必填）
     *   - EncryptCert: 国密双证书的加密证书内容（PEM格式）
     *   - EncryptPrivateKey: 国密双证书的加密证书私钥（PEM格式）
     *   - SignCert: 国密双证书的签名证书内容（PEM格式）
     *   - SignPrivateKey: 国密双证书的签名证书私钥（PEM格式）
     *   - ResourceGroupId: 资源组ID
     *   - Tags: 标签（JSON格式）
     * @return array 响应数据，含 CertId、ResourceId、RequestId
     * @throws \Exception
     */
    public function upload($cert, $key, $params)
    {
        // 验证必填参数
        if (empty($params['Name'])) {
            throw new \Exception('证书名称（Name）不能为空');
        }

        // 解析证书和私钥（支持 PEM 内容或文件路径）
        $certContent = $this->parsePem($cert, '证书');
        $keyContent = $this->parsePem($key, '私钥');

        // 构造请求参数
        $requestParams = [
            'Name' => $params['Name'],
            'Cert' => $certContent,
            'Key' => $keyContent,
        ];

        // 处理国密双证书参数
        if (!empty($params['EncryptCert'])) {
            $requestParams['EncryptCert'] = $this->parsePem($params['EncryptCert'], '国密加密证书');
        }
        if (!empty($params['EncryptPrivateKey'])) {
            $requestParams['EncryptPrivateKey'] = $this->parsePem($params['EncryptPrivateKey'], '国密加密私钥');
        }
        if (!empty($params['SignCert'])) {
            $requestParams['SignCert'] = $this->parsePem($params['SignCert'], '国密签名证书');
        }
        if (!empty($params['SignPrivateKey'])) {
            $requestParams['SignPrivateKey'] = $this->parsePem($params['SignPrivateKey'], '国密签名私钥');
        }

        // 可选参数
        if (isset($params['ResourceGroupId'])) {
            $requestParams['ResourceGroupId'] = $params['ResourceGroupId'];
        }
        if (isset($params['Tags'])) {
            $requestParams['Tags'] = $params['Tags'];
        }

        $result = $this->request('UploadUserCertificate', $requestParams);

        if (isset($result['Code'])) {
            throw new \Exception('Response Error: ' . ($result['Message'] ?? ''));
        }

        return $result;
    }

    /**
     * 部署/更新资源绑定的SSL证书（统一部署接口，封装完整流程）
     * @document https://help.aliyun.com/zh/ssl-certificate/developer-reference/api-cas-2020-04-07-createdeploymentjob
     * @param string $certificateId 证书ID
     * @param array $params 部署参数，如：
     *   - Name: 部署任务名称（必填）
     *   - CloudProduct: 云产品类型（如 CDN、SLB、ALB、WAF、DCDN、OSS、LIVE、VOD 等）
     *   - RegionIds: 地域ID列表（字符串或数组，多个用逗号分隔）
     *   - ContactIds: 联系人ID列表（必填，字符串或数组，多个用逗号分隔）
     *   - ResourceIds: 资源ID列表（可选，不传则自动从 ListCloudResources 获取）
     *   - ScheduleTime: 定时执行时间（Unix时间戳，可选）
     * @return array 响应数据，含 JobId、RequestId
     * @throws \Exception
     */
    public function deployToResources($certificateId, $params)
    {
        // 验证必填参数
        if (empty($params['Name'])) {
            throw new \Exception('部署任务名称（Name）不能为空');
        }
        if (empty($params['ContactIds'])) {
            throw new \Exception('联系人ID（ContactIds）不能为空');
        }

        // 处理 ContactIds 参数（支持数组或逗号分隔的字符串）
        $contactIds = is_array($params['ContactIds']) ? implode(',', $params['ContactIds']) : $params['ContactIds'];

        // 步骤1：获取资源ID列表（如果未提供）
        $resourceIds = $params['ResourceIds'] ?? '';
        if (empty($resourceIds)) {
            // 构造 ListCloudResources 查询参数
            $listParams = [
                'CertIds' => $certificateId,
            ];
            if (!empty($params['CloudProduct'])) {
                $listParams['CloudProduct'] = $params['CloudProduct'];
            }
            if (!empty($params['RegionIds'])) {
                $listParams['RegionIds'] = is_array($params['RegionIds']) ? implode(',', $params['RegionIds']) : $params['RegionIds'];
            }

            $resourceResult = $this->request('ListCloudResources', $listParams);
            if (isset($resourceResult['Code'])) {
                throw new \Exception('查询云资源失败: ' . ($resourceResult['Message'] ?? ''));
            }

            // 提取资源ID
            $resources = $resourceResult['Data'] ?? [];
            if (empty($resources)) {
                throw new \Exception('未找到可部署的云资源，请检查证书ID、云产品类型或地域参数');
            }
            $resourceIdList = array_column($resources, 'Id');
            $resourceIds = implode(',', $resourceIdList);
        } else {
            // 处理传入的 ResourceIds（支持数组或逗号分隔的字符串）
            $resourceIds = is_array($resourceIds) ? implode(',', $resourceIds) : $resourceIds;
        }

        // 步骤2：创建部署任务
        $createParams = [
            'Name' => $params['Name'],
            'JobType' => 'user', // user = 云产品部署
            'CertIds' => $certificateId,
            'ResourceIds' => $resourceIds,
            'ContactIds' => $contactIds,
        ];
        if (isset($params['ScheduleTime'])) {
            $createParams['ScheduleTime'] = $params['ScheduleTime'];
        }

        $createResult = $this->request('CreateDeploymentJob', $createParams);
        if (isset($createResult['Code'])) {
            throw new \Exception('创建部署任务失败: ' . ($createResult['Message'] ?? ''));
        }

        $jobId = $createResult['JobId'] ?? '';
        if (empty($jobId)) {
            throw new \Exception('创建部署任务成功，但未返回 JobId');
        }

        // 步骤3：启动部署任务（将状态修改为待执行）
        $updateParams = [
            'JobId' => $jobId,
            'Status' => 'pending', // pending = 待执行状态
        ];
        $updateResult = $this->request('UpdateDeploymentJobStatus', $updateParams);
        if (isset($updateResult['Code'])) {
            throw new \Exception('启动部署任务失败: ' . ($updateResult['Message'] ?? ''));
        }

        return [
            'JobId' => $jobId,
            'RequestId' => $createResult['RequestId'] ?? '',
            'Message' => '部署任务已创建并启动',
        ];
    }

    /**
     * 查询证书详情
     * @document https://help.aliyun.com/zh/ssl-certificate/developer-reference/api-cas-2020-04-07-getusercertificatedetail
     * @param string $certificateId 证书ID
     * @return array 证书详情，含 Id、Name、Cert、Key、Common、Sans、Issuer、StartDate、EndDate、Expired、Algorithm、CertChain 等
     * @throws \Exception
     */
    public function detail($certificateId)
    {
        if (empty($certificateId)) {
            throw new \Exception('证书ID不能为空');
        }

        $result = $this->request('GetUserCertificateDetail', [
            'CertId' => $certificateId,
        ]);

        if (isset($result['Code'])) {
            throw new \Exception('Response Error: ' . ($result['Message'] ?? ''));
        }

        return $result;
    }

    /**
     * 查询证书绑定的资源列表（查询该证书在哪些云资源上部署/使用）
     * @document https://help.aliyun.com/zh/ssl-certificate/developer-reference/api-cas-2020-04-07-listdeploymentjobresource
     * @param string $certificateId 证书ID
     * @param array $params 其他参数，如：
     *   - CloudProduct: 云产品类型（可选，用于筛选特定产品）
     *   - RegionId: 地域ID（可选，用于筛选特定地域）
     * @return array 资源列表
     * @throws \Exception
     */
    public function listBindings($certificateId, $params = [])
    {
        if (empty($certificateId)) {
            throw new \Exception('证书ID不能为空');
        }

        // 构造查询参数
        $listParams = [
            'CertIds' => $certificateId,
        ];

        // 可选的筛选参数
        if (!empty($params['CloudProduct'])) {
            $listParams['CloudProduct'] = $params['CloudProduct'];
        }
        if (!empty($params['RegionId'])) {
            $listParams['RegionId'] = $params['RegionId'];
        }

        // 调用 ListCloudResources 查询使用该证书的资源
        $result = $this->request('ListCloudResources', $listParams);

        if (isset($result['Code'])) {
            throw new \Exception('Response Error: ' . ($result['Message'] ?? ''));
        }

        return $result['Data'] ?? [];
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
