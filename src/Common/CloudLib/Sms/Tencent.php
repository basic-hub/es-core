<?php

namespace BasicHub\EsCore\Common\CloudLib\Sms;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use TencentCloud\Sms\V20210111\SmsClient;

/**
 * composer require tencentcloud/sms
 */
class Tencent extends Base
{
    protected $secretId = '';

    protected $secretKey = '';

    protected $smsSdkAppId = '';

    protected $templateId = '';

    protected $signName = '';

    protected $phoneNumberSet = [];

    protected $templateParamSet = '';

    /**
     * 地域：
     * @document https://cloud.tencent.com/document/api/213/15692#.E5.9C.B0.E5.9F.9F.E5.88.97.E8.A1.A8
     * 要顺便检查下该地域的推送域名是否存在,格式为 ses.[region].tencentcloudapi.com
     * @document https://cloud.tencent.com/document/api/1288/51055
     * @var string
     */
    protected $region = '';

    protected function getTemplateId()
    {
        if (is_array($this->templateId)) {
            // key不存在则使用默认模板id
            return $this->templateId[$this->templateKey] ?? $this->templateId['default'];
        } else {
            return $this->templateId;
        }
    }

    public function send($to = [], array $params = [])
    {
        $this->phoneNumberSet = is_string($to) ? [$to] : $to;

        $params = array_values(array_map('strval', $params));
        $this->templateParamSet = $params;

        try {
            $Request = new SendSmsRequest();
            $Request->fromJsonString(json_encode([
                'PhoneNumberSet' => $this->phoneNumberSet,
                'SmsSdkAppId' => $this->smsSdkAppId,
                'TemplateId' => $this->getTemplateId(),
                'SignName' => $this->signName ?: null,
                'TemplateParamSet' => $this->templateParamSet,
            ]));

            $endFn = http_tracker('SDK:SMS', [
                'url' => '__TENCENT_SMS__',
                'POST' => $Request->serialize(),
                'method' => 'POST',
            ], $this->parentId);

            $Credential = new Credential($this->secretId, $this->secretKey);
            $Client = new SmsClient($Credential, $this->region);

            // 注意：以下代码可在开发模式下请根据需要开启或关闭
            if (is_env('dev')) {
                return true;
            }

            $resp = $Client->SendSms($Request);

            $str = $resp->toJsonString();
            $array = json_decode($str, true);

            $endFn($array);

            if (isset($array['Error'])) {
                notice("腾讯云发送失败1: $str");
                return false;
            }
            return true;
        } catch (TencentCloudSDKException $e) {
            $msg = '腾讯云短信发送失败2: ' . $e->__toString();
            is_callable($endFn) && $endFn($msg, $e->getCode());

            if (!in_array($e->getErrorCode(), [
                'FailedOperation.UnsupportMailType',
                'InvalidParameterValue.IllegalEmailAddress',
                'InvalidParameterValue.EmailAddressIsNULL',
                'InvalidParameterValue.ReceiverEmailInvalid',
                'MissingParameter.EmailsNecessary'
            ])) {
                notice($msg);
            }

            return false;
        }
    }
}
