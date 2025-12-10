<?php

namespace BasicHub\EsCore\Common\CloudLib\Expense;

use EasySwoole\HttpClient\HttpClient;
use function Swoole\Coroutine\Http\post;

/**
 *  composer require  TencentCloud/Billing
 *  非核心功能尽量不安装依赖，此处使用原生接口实现
 * @document https://cloud.tencent.com/document/product/555/93162
 * @document 响应字段：https://cloud.tencent.com/document/api/555/19183#SummaryDetail
 * /
 */
class Tencent extends Base
{
    protected $secretId = '';

    protected $secretKey = '';

    protected $endpoint = 'https://billing.tencentcloudapi.com';

    protected $region = 'ap-guangzhou';

    /**
     * 腾讯SDK版本
     * @var string
     */
    protected $version = '2018-07-09';

    protected $token = '';

    /**
     * 账单维度，枚举值：business(产品名称)、project(项目)、region(地域)、payMode(计费模式)、tag(标签)
     * @var string
     */
    protected $groupType = 'tag';

    /**
     * 分账标签
     * @var string[]
     */
    protected $tagKey = ['item'];

    /**
     * 查询的月份，Y-m 格式，示例：2025-11
     * @var string
     */
    protected $month = '';

    /**
     * 区分表格名称，当管理几十个账号时，此参数十分有用
     * @var string
     */
    protected $cpname = '';

    public function getExpense()
    {
        $result = $this->request();
        //print_r($result);

        $response = $result['Response'];
        if (empty($response)) {
            throw new \Exception('Response is Empty.');
        }
        if (isset($response['Error'])) {
            throw new \Exception('Response Error');
        }

        // 数据是否准备好，0准备中，1已就绪。（Ready=0，为当前UIN首次进行初始化出账，预计需要5~10分钟出账，请于10分钟后重试即可）
        if ($response['Ready'] !== 1) {
            throw new \Exception('Not ready');
        }

        return $response['SummaryDetail'] ?? [];
    }

    public function echoTable()
    {
        $result = [];
        $expense = $this->getExpense();
        $TotalCostSum = $RealTotalCostSum = $CashPayAmountSum = $VoucherPayAmountSum = 0;
        foreach ($expense as $detail) {
            foreach (['TotalCost', 'RealTotalCost', 'CashPayAmount', 'VoucherPayAmount'] as $col) {
                $varname = $col . 'Sum';
                $$col = floatval($detail[$col] ?: 0);
                $$varname += $$col;
            }

            $tmp = [
                'name' => $detail['GroupValue'],
                'origin' => sprintf('%.2f', $TotalCost),
                'all' => sprintf('%.2f', $RealTotalCost),
                'cash' => sprintf('%.2f', $CashPayAmount),
                'voucher' => sprintf('%.2f', $VoucherPayAmount)
            ];
            $result[] = $tmp;
        }

        // 汇总
        $zcnt = [
            'name' => "$this->cpname - $this->month - 合计",
            'origin' => sprintf('%.2f', $TotalCostSum),
            'all' => sprintf('%.2f', $RealTotalCostSum),
            'cash' => sprintf('%.2f', $CashPayAmountSum),
            'voucher' => sprintf('%.2f', $VoucherPayAmountSum)
        ];
        $result[] = $zcnt;

        $header = [
            'name' => '名称',
            'origin' => '原价',
            'all' => '优惠后总价',
            'cash' => '现金支付',
            'voucher' => '代金券金额'
        ];
        $this->echo($header, $result);
    }

    protected function sign($key, $msg)
    {
        return hash_hmac("sha256", $msg, $key, true);
    }

    /**
     * @return bool|array
     * @throws \Exception
     */
    protected function request()
    {
        $service = 'billing';
        $host = 'billing.tencentcloudapi.com';

        $action = 'DescribeBillSummary';

        $payload = ['Month' => $this->month, 'GroupType' => $this->groupType];
        // 添加标签维度参数
        if ($this->groupType === 'tag') {
            $payload['TagKey'] = $this->tagKey;
        }

        $payload = json_encode($payload);
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
        } catch (Exception $err) {
            echo $err->getMessage();
            return false;
        }
    }
}
