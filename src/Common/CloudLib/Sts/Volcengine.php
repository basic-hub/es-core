<?php

namespace BasicHub\EsCore\Common\CloudLib\Sts;

use Volcengine\Common\Configuration;
use Volcengine\Sts\Model\AssumeRoleRequest;
use Volcengine\Sts\Api\STSApi;

/**
 * composer require volcengine/volcengine-php-sdk v1.0.90
 * 此仓库过大，暂不使用Composer安装
 */
class Volcengine extends Base
{
    protected $ak;
    protected $sk;

    protected $region = 'cn-beijing';

    protected $sessionName = '';

    protected $roleTrn = '';


    /**
     * @document https://www.volcengine.com/docs/6257/86374?lang=zh
     * @apitools https://api.volcengine.com/api-explorer/?action=AssumeRole&groupName=%E8%A7%92%E8%89%B2%E6%89%AE%E6%BC%94&serviceCode=sts&version=2018-01-01&tab=3&tab_sdk=PHP
     * @param $policy 策略语法说明：https://www.volcengine.com/docs/6257/65059?lang=zh
     * @param $expire
     * @return Response
     * @throws \Exception
     */
    public function get($policy, $expire = 1800): Response
    {
        // todo 此Composer仓库过大，希望能在不使用Composer安装的情况下实现，但较为复杂，暂留空，待补充
    }
}
