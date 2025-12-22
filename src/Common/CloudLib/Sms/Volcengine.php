<?php

namespace BasicHub\EsCore\Common\CloudLib\Sms;

use Volc\Service\Sms;
use Volc\Service\Vod\Models\Request\VodApplyUploadInfoRequest;
use Volc\Service\Vod\Models\Response\VodApplyUploadInfoResponse;

/**
 * @install composer require volcengine/volc-sdk-php   火山公共库
 */
class Volcengine extends Base
{
    protected $region = 'cn-guangzhou';

    protected $ak;

    protected $sk;

    /**
     * 消息组ID
     * @var
     */
    protected $smsAccount = '';

    protected $signName = '';

    protected $templateId;

    /**
     * 透传字段，消息回执
     * @var string
     */
    protected $tag;

    protected function getTemplateId()
    {
        return $this->getTplId($this->templateId);
    }

    /**
     * @document API 发送验证码 https://www.volcengine.com/docs/6361/171579?lang=zh
     * @document API 发送短信 https://www.volcengine.com/docs/6361/67380?lang=zh
     * @document SDK https://www.volcengine.com/docs/6361/1109262?lang=zh
     * @param $to
     * @param array $params
     * @return void
     */
    public function send($to = [], array $params = [])
    {
        $client = Sms::getInstance($this->region);// 不传默认cn-north-1，可选ap-singapore-1 新加坡
        $client->setAccessKey($this->ak);
        $client->setSecretKey($this->sk);

        try {

            $phoneNumbers = implode(',', is_string($to) ? [$to] : $to);

            $body = [
                'SmsAccount' => $this->smsAccount,
                'Sign' => $this->signName,
                'TemplateID'=> $this->getTemplateId(),
                'TemplateParam'=> json_encode($params),
                'PhoneNumbers'=> $phoneNumbers,
            ];
            if (!empty($this->tag)) {
                $body['Tag'] = $this->tag;
            }

            $endFn = http_tracker('SDK:SMS', [
                'url' => '__VOLC_SMS__',
                'POST' => $body,
                'method' => 'POST',
            ], $this->parentId);

            /** @var VodApplyUploadInfoResponse $response */
            $response = $client->sendSms(['json' => $body]);

            $ResponseMetadata = $response->getResponseMetadata();
            $endFn([
                'ResponseMetadata' => $ResponseMetadata,
                'Result' => $response->getResult(),
            ]);

            if (! in_array($errCode = $ResponseMetadata->getError()->getCode(), [
                'RE:0006', // 手机号格式错误
            ])) {
                $errMsg = $ResponseMetadata->getError()->getMessage();
                notice("火山云短信发送失败, errCode=$errCode, errMsg=$errMsg");
            }

            return ! $ResponseMetadata->hasError();
        } catch (\Exception $e) {
            $msg = '火山云短信发送失败: ' . $e->__toString();
            is_callable($endFn) && $endFn($msg, $e->getCode());

            trace($msg, 'error');

            return false;
        }
    }
}
