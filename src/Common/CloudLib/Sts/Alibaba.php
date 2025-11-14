<?php

namespace BasicHub\EsCore\Common\CloudLib\Sts;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Sts\V20150401\AssumeRole;
use EasySwoole\EasySwoole\Config;
use EasySwoole\Utility\Random;

/**
 * composer require alibabacloud/sts-20150401
 * composer require alibabacloud/client
 * @document https://help.aliyun.com/zh/oss/developer-reference/use-temporary-access-credentials-provided-by-sts-to-access-oss?spm=5176.21213303.J_ZGek9Blx07Hclc3Ddt9dg.1.670c2f3dzzj3IV&scm=20140722.S_help@@%E6%96%87%E6%A1%A3@@100624._.ID_help@@%E6%96%87%E6%A1%A3@@100624-RL_%E4%B8%B4%E6%97%B6%E5%AF%86%E9%92%A5-LOC_2024SPHelpResult-OR_ser-PAR1_213e04f017631078574016218e21cb-V_4-PAR3_o-RE_new7-P0_0-P1_0#b8aeaf6650mnz
 * @document https://help.aliyun.com/zh/ram/developer-reference/sts-sdk-overview?spm=a2c4g.11186623.0.0.755d26edZsPb8u#reference-w5t-25v-xdb
 * @document https://next.api.aliyun.com/api/Sts/2015-04-01/AssumeRole?RegionId=cn-hangzhou&params={%22DurationSeconds%22:14400,%22Policy%22:%22dsddd%22,%22RoleArn%22:%22sss%22,%22RoleSessionName%22:%22fdsafdsaf%22,%22ExternalId%22:%225435%22,%22SourceIdentity%22:%2254353%22}&tab=DEMO&lang=PHP
 */
class Alibaba extends Base
{
    protected $accessKeyId;
    protected $accessKeySecret;
    protected $endpoint;


    public function get($policy, $expire = 1800): Response
    {
        // todo 阿里云sts待补充
        return new Response();
    }
}
