<?php

namespace BasicHub\EsCore\Common\CloudLib\Domain;

use EasySwoole\HttpClient\HttpClient;

/**
 *  火山引擎域名服务
 *  非核心功能尽量不安装依赖，此处使用原生接口实现（HMAC-SHA256 签名，GET 方式）
 * @document 获取域名列表 ListDomains：https://www.volcengine.com/docs/6568/163747
 * @document 签名机制：https://www.volcengine.com/docs/6369/67269
 */
class Volcengine extends Base
{
    protected $ak = '';

    protected $sk = '';

    protected $endpoint = 'https://open.volcengineapi.com';

    /**
     * 火山引擎API版本
     * @var string
     */
    protected $version = '2022-12-12';

    /**
     * 服务名（用于签名 CredentialScope）
     * @var string
     */
    protected $service = 'domain_openapi';

    /**
     * 地域（域名服务固定 cn-north-1）
     * @var string
     */
    protected $region = 'cn-north-1';

    /**
     * 单页拉取条数，接口上限50
     * @var int
     */
    protected $limit = 50;

    /**
     * 获取域名列表（单页）
     * @param array $params 接口参数（snake_case），如 domain/status/verify_status/domain_name_audit_status/expired_after/is_auto_renew/order_by/asc_or_desc/page_number/page_size
     * @return array Result body，含 domain_info_list、total
     * @throws \Exception
     */
    public function list($params = [])
    {
        $result = $this->request('ListDomains', $params);

        $responseMetadata = $result['ResponseMetadata'] ?? [];
        if (isset($responseMetadata['Error'])) {
            throw new \Exception('Response Error: ' . ($responseMetadata['Error']['Message'] ?? ''));
        }

        return $result['Result'] ?? [];
    }

    /**
     * 获取账号下所有域名（自动分页拉取）
     * @param array $params 接口参数（无需传 page_number/page_size，内部自动处理）
     * @return array 全部 domain_info_list
     * @throws \Exception
     */
    public function listAll($params = [])
    {
        $limit = !empty($params['page_size']) ? min(intval($params['page_size']), 50) : $this->limit;
        $page = 1;
        $all = [];

        do {
            $params['page_size'] = $limit;
            $params['page_number'] = $page;
            // 默认按到期时间升序（快到期的排在最前面）
            if (!isset($params['order_by'])) {
                $params['order_by'] = 'expired_time';
            }
            if (!isset($params['asc_or_desc'])) {
                $params['asc_or_desc'] = 'ASC';
            }

            $response = $this->list($params);
            $domains = $response['domain_info_list'] ?? [];
            $total = $response['total'] ?? 0;

            $all = array_merge($all, $domains);
            $page++;
        } while (count($all) < $total && !empty($domains));

        // 兜底排序：按到期时间升序（快到期的排在最前面）
        usort($all, function ($a, $b) {
            $ta = $a['expired_time'] ?? 0;
            $tb = $b['expired_time'] ?? 0;
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
            // 火山引擎返回 Unix 时间戳，转换为日期字符串
            $begin = !empty($domain['register_time']) ? date('Y-m-d H:i:s', $domain['register_time']) : '';
            $end = !empty($domain['expired_time']) ? date('Y-m-d H:i:s', $domain['expired_time']) : '';
            $days = $this->calcDomainDays($begin, $end);
            $remain = $this->calcRemainDays($end);
            $result[] = [
                'domain' => $domain['domain'] ?? '',
                'zone' => $domain['zone'] ?? '',
                'status' => $this->statusMap($domain['status'] ?? ''),
                'verify' => $this->verifyMap($domain['verify_status'] ?? ''),
                'autoRenew' => $this->autoRenewMap($domain['is_auto_renew'] ?? null),
                'days' => $days,
                'remain' => $remain,
                'begin' => $begin,
                'end' => $end,
                'id' => $domain['instance_no'] ?? '',
            ];
            $rowColors[] = $this->rowColor($remain);
        }

        $header = [
            'domain' => '域名',
            'zone' => '后缀',
            'status' => '状态',
            'verify' => '实名认证',
            'autoRenew' => '自动续费',
            'days' => '域名天数',
            'remain' => '剩余天数',
            'begin' => '注册时间',
            'end' => '到期时间',
            'id' => '实例ID',
        ];
        $this->echo($header, $result, $rowColors);
    }

    /**
     * 火山引擎域名状态枚举转中文
     * @param string $status
     * @return string
     */
    protected function statusMap($status)
    {
        $map = [
            'normal'              => '正常',
            'registrant_change'   => '过户中',
            'expired'             => '已过期',
            'redemption'          => '赎回期',
            'wasted'              => '已失去',
            'transfer_out'        => '已转出',
            'transferring'        => '转移中',
            'redeeming'           => '赎回中',
            'registrant_changing' => '信息更新中',
        ];
        return isset($map[$status]) ? $map[$status] : $status;
    }

    /**
     * 火山引擎域名实名认证状态枚举转中文
     * @param string $status
     * @return string
     */
    protected function verifyMap($status)
    {
        $map = [
            'not_validation'         => '未认证',
            'pending_validation'     => '认证中',
            'verification_success'   => '已认证',
            'verification_failed'    => '认证失败',
        ];
        return isset($map[$status]) ? $map[$status] : $status;
    }

    /**
     * 火山引擎域名自动续费状态转中文
     * @param bool|null $status
     * @return string
     */
    protected function autoRenewMap($status)
    {
        if ($status === true) {
            return '已开启';
        }
        if ($status === false) {
            return '未开启';
        }
        return '-';
    }

    /**
     * RFC3986 规范的 URL 编码（火山引擎签名专用）
     * @param string $value
     * @return string
     */
    protected function percentEncode($value)
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } else {
            $value = (string)$value;
        }
        $encoded = rawurlencode($value);
        return $encoded;
    }

    /**
     * @param string $action 接口动作名，如 ListDomains
     * @param array  $params 接口业务参数（GET 查询参数）
     * @return bool|array
     * @throws \Exception
     */
    protected function request($action, array $params)
    {
        $host = 'open.volcengineapi.com';

        // 公共查询参数
        $queryParams = [
            'Action' => $action,
            'Version' => $this->version,
        ];
        // 业务参数
        foreach ($params as $k => $v) {
            $queryParams[$k] = $v;
        }

        // 参数按字典序升序排序
        ksort($queryParams);

        // 拼接规范查询串（用于签名）
        $canonicalQuery = '';
        foreach ($queryParams as $k => $v) {
            $canonicalQuery .= '&' . $this->percentEncode($k) . '=' . $this->percentEncode($v);
        }
        $canonicalQuery = substr($canonicalQuery, 1);

        // GET 请求无 body，payload 为空串
        $payload = '';
        $hashedPayload = hash('sha256', $payload);

        // X-Date：ISO 8601 UTC，格式 YYYYMMDDTHHMMSSZ
        $xDate = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');

// ************* 步骤 1：拼接规范请求串 *************
        $canonicalHeaders = "host:$host\nx-date:$xDate\n";
        $signedHeaders = 'host;x-date';
        $canonicalRequest = "GET\n/\n$canonicalQuery\n$canonicalHeaders\n$signedHeaders\n$hashedPayload";

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
            'Host' => $host,
            'X-Date' => $xDate,
        ];

        $url = $this->endpoint . '/?' . $canonicalQuery;

        try {
            $HttpClient = new HttpClient($url);
            $Resp = $HttpClient->setHeaders($headers, false, false)->get();
            return $Resp->json(true);
        } catch (\Exception $err) {
            echo $err->getMessage();
            return false;
        }
    }
}
