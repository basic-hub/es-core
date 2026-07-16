<?php

namespace BasicHub\EsCore\Common\CloudLib\Domain;

use EasySwoole\HttpClient\HttpClient;

/**
 *  阿里云域名服务
 *  非核心功能尽量不安装依赖，此处使用原生接口实现（RPC 风格签名 HMAC-SHA1）
 * @document 分页查询域名列表 QueryDomainList：https://help.aliyun.com/zh/domain/developer-reference/api-domain-2018-01-29-querydomainlist
 * @document 签名机制：https://help.aliyun.com/zh/user-guide/overview-of-rpc-api-signatures
 */
class Alibaba extends Base
{
    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    protected $endpoint = 'https://domain.aliyuncs.com';

    /**
     * 阿里云API版本
     * @var string
     */
    protected $version = '2018-01-29';

    /**
     * 单页拉取条数
     * @var int
     */
    protected $limit = 100;

    /**
     * 获取域名列表（单页）
     * @param array $params 接口参数（PascalCase），如 PageNum/PageSize/DomainName/QueryType/OrderByType/OrderKeyType/DomainGroupId/StartExpirationDate/EndExpirationDate/ProductDomainType
     *   - QueryType: 1=急需续费列表, 2=急需赎回列表
     *   - OrderKeyType: RegistrationDate=注册时间排序, ExpirationDate=到期时间排序
     *   - OrderByType: ASC=升序, DESC=降序
     * @return array Response body，含 Data、TotalPageNum、CurrentPageNum、PageSize、RequestId
     * @throws \Exception
     */
    public function list($params = [])
    {
        $result = $this->request('QueryDomainList', $params);

        if (isset($result['Code'])) {
            throw new \Exception('Response Error: ' . ($result['Message'] ?? ''));
        }

        return $result;
    }

    /**
     * 获取账号下所有域名（自动分页拉取）
     * @param array $params 接口参数（无需传 PageNum/PageSize，内部自动处理）
     * @return array 全部 Data.Domain
     * @throws \Exception
     */
    public function listAll($params = [])
    {
        $limit = !empty($params['PageSize']) ? min(intval($params['PageSize']), 100) : $this->limit;
        $page = 1;
        $all = [];

        do {
            $params['PageSize'] = $limit;
            $params['PageNum'] = $page;

            $response = $this->list($params);
            $domains = $response['Data']['Domain'] ?? [];
            $totalPage = $response['TotalPageNum'] ?? 0;

            $all = array_merge($all, $domains);
            $page++;
        } while ($page <= $totalPage && !empty($domains));

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
            $begin = $domain['RegistrationDate'] ?? '';
            $end = $domain['ExpirationDate'] ?? '';
            $days = $this->calcDomainDays($begin, $end);
            $remain = $this->calcRemainDays($end);
            $result[] = [
                'domain' => $domain['DomainName'] ?? '',
                'audit' => $this->auditMap($domain['DomainAuditStatus'] ?? ''),
                'status' => $this->statusMap($domain['ExpirationDateStatus'] ?? ''),
                'autoRenew' => '-',
                'days' => $days,
                'remain' => $remain,
                'begin' => $begin,
                'end' => $end,
                'id' => $domain['InstanceId'] ?? '',
            ];
            $rowColors[] = $this->rowColor($remain);
        }

        $header = [
            'domain' => '域名',
            'audit' => '实名认证',
            'status' => '过期状态',
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
     * 阿里云域名过期状态枚举转中文
     * @param string $status
     * @return string
     */
    protected function statusMap($status)
    {
        $map = [
            '1' => '未过期',
            '2' => '已过期',
        ];
        return isset($map[$status]) ? $map[$status] : $status;
    }

    /**
     * 阿里云域名实名认证状态枚举转中文
     * @param string $status
     * @return string
     */
    protected function auditMap($status)
    {
        $map = [
            'SUCCEED'  => '认证成功',
            'FAILED'   => '认证失败',
            'NONAUDIT' => '未认证',
            'AUDITING' => '审核中',
        ];
        return isset($map[$status]) ? $map[$status] : $status;
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
     * @param string $action 接口动作名，如 QueryDomainList
     * @param array  $params 接口业务参数
     * @return bool|array
     * @throws \Exception
     */
    protected function request($action, array $params)
    {
        $host = 'domain.aliyuncs.com';

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
