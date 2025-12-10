<?php

namespace BasicHub\EsCore\Common\CloudLib\Expense;

use AlibabaCloud\SDK\BssOpenApi\V20171214\Models\DescribeSplitItemBillRequest;
use AlibabaCloud\SDK\BssOpenApi\V20171214\Models\QueryBillOverviewRequest;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use AlibabaCloud\SDK\BssOpenApi\V20171214\BssOpenApi;

/**
 * todo 未完成
 * composer require alibabacloud/bssopenapi-20171214 4.1.0
 * 必选，Composer安装
 * @document https://help.aliyun.com/zh/user-center/developer-reference/api-bssopenapi-2017-12-14-describesplititembill
 * @document 错误码  https://help.aliyun.com/zh/user-center/developer-reference/api-bssopenapi-2017-12-14-errorcodes?spm=a2c4g.11186623.help-menu-100369.d_8_1_5.12994f4aKVeHoI&scm=20140722.H_473041._.OR_help-T_cn~zh-V_1
 * @document 阿里云RequestId诊断 https://api.aliyun.com/troubleshoot?q=InvalidVersion&product=BssOpenApi&requestId=xxxxxxx
 */
class Alibaba extends Base
{
    /**
     * 密钥权限，只读，AliyunBSSReadOnlyAccess
     * @var string
     */
    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    /**
     * 地域和端点
     * @document https://api.aliyun.com/product/BssOpenApi
     * @var string
     */
    protected $endpoint = 'https://business.aliyuncs.com';
    protected $regionId = 'cn-shanghai';

    /**
     * 查询的月份，Y-m 格式，示例：2025-11
     * @var string
     */
    protected $month = '';

    /**
     * 分账标签
     * @var string
     */
    protected $tagKey = 'item';

    public function getExpense()
    {
        return $this->request();
    }

    public function echoTable()
    {

    }

    /**
     * 拉取阿里云账号按月账单
     * @throws \Exception
     */
    protected function request()
    {
        $Config = new Config([
            'regionId' => $this->regionId,
            'accessKeyId' => $this->accessKeyId,
            'accessKeySecret' => $this->accessKeySecret
        ]);
        $Client = new BssOpenApi($Config);

        $tagFilter0 = new DescribeSplitItemBillRequest\tagFilter([
            'tagKey' => $this->tagKey
        ]);
        $describeSplitItemBillRequest = new DescribeSplitItemBillRequest([
//            'billingDate' => $this->month,
            'billingCycle' => $this->month,
            'maxResults' => 300, // 最大值
            'tagFilter' => [
                $tagFilter0
            ]
        ]);

        $runtime = new RuntimeOptions([]);
        return $Client->describeSplitItemBillWithOptions($describeSplitItemBillRequest, $runtime);
    }
}
