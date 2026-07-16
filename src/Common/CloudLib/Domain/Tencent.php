<?php

namespace BasicHub\EsCore\Common\CloudLib\Domain;

use EasySwoole\HttpClient\HttpClient;

/**
 *  腾讯云域名注册服务
 *  非核心功能尽量不安装依赖，此处使用原生接口实现（TC3-HMAC-SHA256 签名）
 * @document 我的域名列表 DescribeDomainNameList：https://cloud.tencent.com/document/api/242/48941
 */
class Tencent extends Base
{
    protected $secretId = '';

    protected $secretKey = '';

    protected $endpoint = 'https://domain.tencentcloudapi.com';

    /**
     * 腾讯域名接口不需要传递Region
     * @var string
     */
    protected $region = '';

    /**
     * 腾讯SDK版本
     * @var string
     */
    protected $version = '2018-08-08';

    protected $token = '';

    /**
     * 单页拉取条数，接口上限100
     * @var int
     */
    protected $limit = 100;

    /**
     * 获取域名列表（单页）
     * @param array $params 接口参数，如 Offset/Limit
     * @return array Response body，含 DomainSet、TotalCount、RequestId
     * @throws \Exception
     */
    public function list($params = [])
    {
        $result = $this->request('DescribeDomainNameList', $params);

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
     * 获取账号下所有域名（自动分页拉取）
     * @param array $params 接口参数（无需传 Offset/Limit，内部自动处理）
     * @return array 全部 DomainSet
     * @throws \Exception
     */
    public function listAll($params = [])
    {
        $limit = !empty($params['Limit']) ? min(intval($params['Limit']), 100) : $this->limit;
        $offset = 0;
        $all = [];

        do {
            $params['Limit'] = $limit;
            $params['Offset'] = $offset;

            $response = $this->list($params);
            $domains = $response['DomainSet'] ?? [];
            $total = $response['TotalCount'] ?? 0;

            $all = array_merge($all, $domains);
            $offset += $limit;
        } while ($offset < $total && !empty($domains));

        // 默认按到期时间升序（快到期的排在最前面）
        usort($all, function ($a, $b) {
            $ta = strtotime($a['ExpirationDate'] ?? '') ?: 0;
            $tb = strtotime($b['ExpirationDate'] ?? '') ?: 0;
            return $ta <=> $tb;
        });

        return $all;
    }

    /**
     * 将域名列表输出为控制台表格
     * @param array $params 接口参数，同 listAll
     * @return void
     * @throws \Exception
     */
    public function echoTable($params = [])
    {
        $domains = $this->listAll($params);

        $result = [];
        $rowColors = [];
        foreach ($domains as $domain) {
            $begin = $domain['CreationDate'] ?? '';
            $end = $domain['ExpirationDate'] ?? '';
            $days = $this->calcDomainDays($begin, $end);
            $remain = $this->calcRemainDays($end);
            $result[] = [
                'domain' => $domain['DomainName'] ?? '',
                'tld' => $domain['Tld'] ?? '',
                'status' => $this->statusMap($domain['BuyStatus'] ?? ''),
                'autoRenew' => $this->autoRenewMap($domain['AutoRenew'] ?? ''),
                'days' => $days,
                'remain' => $remain,
                'begin' => $begin,
                'end' => $end,
                'id' => $domain['DomainId'] ?? '',
            ];
            $rowColors[] = $this->rowColor($remain);
        }

        $header = [
            'domain' => '域名',
            'tld' => '后缀',
            'status' => '状态',
            'autoRenew' => '自动续费',
            'days' => '域名天数',
            'remain' => '剩余天数',
            'begin' => '注册时间',
            'end' => '到期时间',
            'id' => '域名ID',
        ];
        $this->echo($header, $result, $rowColors);
    }

    /**
     * 腾讯云域名购买状态枚举转中文
     * @param string $status
     * @return string
     */
    protected function statusMap($status)
    {
        $map = [
            'Normal'         => '正常',
            'AboutToExpire'  => '即将到期',
            'RenewRequired'  => '需续费',
            'RedeemRequired' => '需赎回',
            'Expired'        => '已过期',
            'Deleted'        => '已删除',
        ];
        return isset($map[$status]) ? $map[$status] : $status;
    }

    /**
     * 腾讯云域名自动续费状态枚举转中文
     * @param int $status
     * @return string
     */
    protected function autoRenewMap($status)
    {
        $map = [
            0 => '未设置',
            1 => '已开启',
            2 => '到期不续',
        ];
        $status = intval($status);
        return isset($map[$status]) ? $map[$status] : (string)$status;
    }

    protected function sign($key, $msg)
    {
        return hash_hmac('sha256', $msg, $key, true);
    }

    /**
     * @param string $action  接口动作名，如 DescribeDomainNameList
     * @param array  $params  接口业务参数
     * @return bool|array
     * @throws \Exception
     */
    protected function request($action, array $params)
    {
        $service = 'domain';
        $host = 'domain.tencentcloudapi.com';

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
