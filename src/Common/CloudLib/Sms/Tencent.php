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
        return $this->getTplId($this->templateId);
    }

    /**
     * @document https://cloud.tencent.com/document/product/382/55981
     * @param string|array $to
     * @param array $params
     * @return bool
     */
    public function send($to = [], array $params = [])
    {
        if ($this->isdebug) {
            trace(__METHOD__ . ', to=' . var_export($to, true) . ', params=' . var_export($params, true));
            return true;
        }

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

            trace($msg, 'error');

            if (!in_array($e->getErrorCode(), [
                'InvalidParameterValue.IncorrectPhoneNumber',
                'MissingParameter.EmptyPhoneNumberSet',
            ])) {
                notice($msg);
            }

            return false;
        }
    }
}
