<?php

namespace BasicHub\EsCore\Common\CloudLib\Domain;

use EasySwoole\HttpClient\HttpClient;

/**
 *  GoDaddy 域名服务
 *  非核心功能尽量不安装依赖，此处使用原生接口实现（PAT Bearer Token 认证）
 * @document 认证文档：https://developer.godaddy.com/en/docs/api-users/auth
 * @document 域名列表：https://developer.godaddy.com/en/docs/api-users/manage-domains/list
 *  PAT 获取：https://developer.godaddy.com/personal-access-token
 *  请求示例：GET https://api.godaddy.com/v1/domains?limit=100&offset=0
 *  认证头：Authorization: Bearer YOUR_PAT
 *  响应结构：JSON 数组，每个元素含 domain/domainId/status/expires/createdAt/renewAuto 等字段
 */
class Godaddy extends Base
{
    /**
     * Personal Access Token（PAT）
     * 在 https://developer.godaddy.com/personal-access-token 生成
     * 生成时需勾选 domains.domain:read 权限范围
     * @var string
     */
    protected $pat = '';

    protected $endpoint = 'https://api.godaddy.com/v1/domains';

    /**
     * 单页拉取条数
     * @var int
     */
    protected $limit = 100;

    /**
     * HTTP 代理地址（可选）
     * 留空则不使用代理（直连）
     * 国内网络环境需要增加Clash代理，7897就是默认的端口
     * 格式：http://127.0.0.1:7897 或 socks5://127.0.0.1:1080
     * @var string
     */
    protected $proxy = '';

    /**
     * 获取域名列表（单页）
     * @param array $params 接口参数，如 limit/offset/status/includes
     * @return array 域名数组
     * @throws \Exception
     */
    public function list($params = [])
    {
        $result = $this->request($params);

        if (!is_array($result)) {
            throw new \Exception('Response Error: ' . $result);
        }

        return $result;
    }

    /**
     * 获取账号下所有域名（自动分页拉取）
     * GoDaddy v1 使用游标分页：marker = 上一页最后一个域名，非 offset
     * @param array $params 接口参数（无需传 limit/marker，内部自动处理）
     * @return array 全部域名
     * @throws \Exception
     */
    public function listAll($params = [])
    {
        $limit = !empty($params['limit']) ? intval($params['limit']) : $this->limit;
        $all = [];

        // 移除外部传入的 offset/marker，由内部管理
        unset($params['offset'], $params['marker']);

        do {
            $params['limit'] = $limit;

            $domains = $this->list($params);

            $all = array_merge($all, $domains);

            // 游标分页：marker 设为本页最后一个域名的 domain 字段
            if (count($domains) >= $limit) {
                $last = end($domains);
                $params['marker'] = $last['domain'] ?? '';
            }
            // 当返回条数少于 limit 时，说明已到最后一页
        } while (count($domains) >= $limit && !empty($domains));

        // 默认按到期时间升序（快到期的排在最前面）
        usort($all, function ($a, $b) {
            $ta = strtotime($a['expires'] ?? '') ?: 0;
            $tb = strtotime($b['expires'] ?? '') ?: 0;
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
            $begin = $domain['createdAt'] ?? '';
            $end = $domain['expires'] ?? '';
            $days = $this->calcDomainDays($begin, $end);
            $remain = $this->calcRemainDays($end);
            $result[] = [
                'domain' => $domain['domain'] ?? '',
                'status' => $this->statusMap($domain['status'] ?? ''),
                'autoRenew' => $this->autoRenewMap($domain['renewAuto'] ?? null),
                'days' => $days,
                'remain' => $remain,
                'begin' => $begin,
                'end' => $end,
                'id' => $domain['domainId'] ?? '',
            ];
            $rowColors[] = $this->rowColor($remain);
        }

        $header = [
            'domain' => '域名',
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
     * GoDaddy 域名状态枚举转中文
     * @param string $status
     * @return string
     */
    protected function statusMap($status)
    {
        $map = [
            'ACTIVE'           => '正常',
            'EXPIRED'          => '已过期',
            'EXPIRING'         => '即将到期',
            'REDEMPTION'       => '赎回期',
            'PENDING'          => '处理中',
            'CANCELLED'        => '已取消',
            'TRANSFER'         => '转移中',
            'TRANSFERRED_OUT'  => '已转出',
            'DISABLED'         => '已禁用',
            'PENDING_RENEWAL'  => '续费中',
            'PENDING_TRANSFER' => '转移中',
            'PENDING_RESTORE'  => '恢复中',
        ];
        return isset($map[$status]) ? $map[$status] : $status;
    }

    /**
     * GoDaddy 域名自动续费状态转中文
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
     * @param array $params 接口业务参数（GET 查询参数）
     * @return bool|array|string
     * @throws \Exception
     */
    protected function request(array $params)
    {
        // 构造查询串
        $query = '';
        foreach ($params as $k => $v) {
            $query .= '&' . urlencode($k) . '=' . urlencode($v);
        }
        $query = ltrim($query, '&');

        $url = $this->endpoint . ($query ? '?' . $query : '');

        $headers = [
            'Authorization' => 'Bearer ' . $this->pat,
            'Accept' => 'application/json',
        ];

        try {
            $HttpClient = new HttpClient($url);
            // 禁用 SSL 证书校验（WSL2 环境 CA 证书可能缺失）
            $HttpClient->setSslVerifyPeer(false);
            $HttpClient->setClientSetting('connect_timeout', 30);
            $HttpClient->setClientSetting('timeout', 30);
            // 配置代理（中国大陆访问 api.godaddy.com 经常超时，需走代理）
            // 支持 http://host:port 或 socks5://host:port 格式
            if ($this->proxy) {
                $proxyParts = parse_url($this->proxy);
                if (!empty($proxyParts['scheme']) && stripos($proxyParts['scheme'], 'socks') !== false) {
                    $HttpClient->setProxySocks5(
                        $proxyParts['host'],
                        intval($proxyParts['port'] ?? 1080),
                        $proxyParts['user'] ?? null,
                        $proxyParts['pass'] ?? null
                    );
                } else {
                    $HttpClient->setProxyHttp(
                        $proxyParts['host'],
                        intval($proxyParts['port'] ?? 8080),
                        $proxyParts['user'] ?? null,
                        $proxyParts['pass'] ?? null
                    );
                }
            }
            $Resp = $HttpClient->setHeaders($headers, false, false)->get();
            $body = $Resp->getBody();
            $statusCode = $Resp->getStatusCode();

            // Swoole 协程客户端层错误（DNS/连接/SSL 等），通过 errCode/errMsg 获取
            $errCode = $Resp->getErrCode();
            $errMsg = $Resp->getErrMsg();
            if ($errCode !== 0) {
                return sprintf('[Swoole errCode=%d errMsg=%s]', $errCode, $errMsg);
            }

            $data = $Resp->json(true);
            // 解析成功直接返回
            if (is_array($data)) {
                return $data;
            }

            // 解析失败：返回包含状态码与原始响应体的错误信息，便于定位
            return sprintf('[HTTP %d] %s', $statusCode, $body);
        } catch (\Exception $err) {
            return 'Request Exception: ' . $err->getMessage();
        }
    }
}
