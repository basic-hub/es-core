<?php

namespace BasicHub\EsCore\Common\CloudLib\Sms;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use Darabonba\OpenApi\Models\Config;

/**
 * composer require alibabacloud/dysmsapi-20170525
 */
class Alibaba extends Base
{
    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    protected $endpoint = 'dysmsapi.aliyuncs.com';

    protected $signName = '';

    protected $templateCode = '';

    protected $phoneNumbers = '';

    protected $templateParam = '';

    protected function getTemplateId()
    {
        return $this->getTplId($this->templateCode);
    }

    /**
     * @document https://help.aliyun.com/zh/sms/developer-reference/api-error-codes?spm=a2c4g.11186623.help-menu-44282.d_5_6.3f4258d0OBDUKp
     * @param $to
     * @param array $params
     * @return bool
     */
    public function send($to = [], array $params = [])
    {
        if ($this->isdebug) {
            trace(__METHOD__ . ', to=' . var_export($to, true) . ', params=' . var_export($params, true));
            return true;
        }

        $this->phoneNumbers = implode(',', is_string($to) ? [$to] : $to);
        $this->templateParam = json_encode($params);

        $log = repeat_array_keys(get_object_vars($this), ['accessKeyId', 'accessKeySecret'], 5);
        $endFn = http_tracker('SDK:SMS', [
            'url' => '__ALI_SMS__',
            'POST' => $log,
            'method' => 'POST',
        ], $this->parentId);

        try {
            $Runtime = new RuntimeOptions();
            $Request = new SendSmsRequest([
                'phoneNumbers' => $this->phoneNumbers,
                'signName' => $this->signName,
                'templateCode' => $this->getTemplateId(),
                'templateParam' => $this->templateParam
            ]);
            $Config = new Config([
                "accessKeyId" => $this->accessKeyId,
                "accessKeySecret" => $this->accessKeySecret
            ]);
            // 访问的域名
            $Config->endpoint = $this->endpoint;
            $Client = new Dysmsapi($Config);

            $resp = $Client->sendSmsWithOptions($Request, $Runtime);
            $arr = $resp->toMap();
            $endFn($arr, 200);

            $isSuccess = $resp->body->code === 'OK';

            if ( ! $isSuccess) {
                notice('阿里云短信发送失败: ' . $resp->body->message);
            }
            return $isSuccess;
        } catch (\Exception $error) {
            if ( ! ($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }

            is_callable($endFn) && $endFn($error->__toString(), $error->getCode());

            trace($msg = '阿里云短信发送失败: ' . $error->__toString(), 'error');

            if (!in_array($error->getCode(), [
                'isv.MOBILE_NUMBER_ILLEGAL',
            ])) {
                notice($msg);
            }

            return false;
        }
    }
}
