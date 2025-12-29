<?php

namespace BasicHub\EsCore\Common\CloudLib\Email;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Ses\V20201002\Models\DeleteBlackListRequest;
use TencentCloud\Ses\V20201002\Models\ListBlackEmailAddressRequest;
use TencentCloud\Ses\V20201002\Models\SendEmailRequest;
use TencentCloud\Ses\V20201002\SesClient;

/**
 * composer require tencentcloud/ses
 */
class Tencent extends Base
{
    protected $secretId = '';

    protected $secretKey = '';

    /**
     *  发件人，邮箱格式
     * @var string
     */
    protected $fromEmailAddress = '';

    protected $subject = '';

    protected $templateId = '';

    /**
     * 收件人
     * @var string
     */
    protected $destination = [];

    /**
     * 模板变量
     * @var string
     */
    protected $templateData = '';

    /**
     * 地域：
     * @document https://cloud.tencent.com/document/api/213/15692#.E5.9C.B0.E5.9F.9F.E5.88.97.E8.A1.A8
     * 要顺便检查下该地域的推送域名是否存在,格式为 ses.[region].tencentcloudapi.com
     * @document https://cloud.tencent.com/document/api/1288/51034
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

    /**
     * @document 关于黑名单描述 https://cloud.tencent.com/document/product/1288/51031
     * @document 发送邮件 https://cloud.tencent.com/document/product/1288/51034
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

        settype($to, 'array');

        $destination = $to ?: $this->destination;

        try {
            $Request = new SendEmailRequest();
            $Request->fromJsonString(json_encode([
                'FromEmailAddress' => $this->fromEmailAddress,
                // 最多支持50人
                'Destination' => $destination,
                'Subject' => $this->subject,
                'Template' => [
                    // 腾讯云的此参数要求为int
                    'TemplateID' => intval($this->getTemplateId()),
                    'TemplateData' => json_encode($params),
                ],
            ]));

            $endFn = http_tracker('SDK:EMAIL', [
                'url' => '__TENCENT_EMAIL__',
                'POST' => $Request->serialize(),
                'method' => 'POST',
            ], $this->parentId);

            $Credential = new Credential($this->secretId, $this->secretKey);
            $Client = new SesClient($Credential, $this->region);

            $resp = $Client->SendEmail($Request);

            $str = $resp->toJsonString();
            $array = json_decode($str, true);

            $endFn($array);

            if (isset($array['Error'])) {
                trace("腾讯云邮件发送失败1: $str", 'error');
                return false;
            }

            return true;
        } catch (TencentCloudSDKException $e) {
            $msg = "腾讯云邮件发送失败2: " . $e->__toString() . ', 收件地址：' . (is_array($destination) ? implode(',', $destination) : $destination);
            is_callable($endFn) && $endFn($msg, $e->getCode());

            trace($msg, 'error');

            if (!in_array($e->getErrorCode(), [
                'FailedOperation.UnsupportMailType',
                'InvalidParameterValue.IllegalEmailAddress',
                'InvalidParameterValue.EmailAddressIsNULL',
                'InvalidParameterValue.ReceiverEmailInvalid',
                'MissingParameter.EmailsNecessary',
                'FailedOperation.EmailAddrInBlacklist', // 关于黑名单的描述请参考下面文档，基本原因都是因为邮箱地址不存在
                'FailedOperation.FrequencyLimit', // 触发频率控制，短时间内对同一地址发送过多邮件。
                'FailedOperation.IncorrectEmail', // 邮箱地址错误。
            ])) {
                notice($msg);
            }

            return false;
        }
    }

    /**
     * 腾讯云发送的邮件一旦被收件方判断为硬退(Hard Bounce)，腾讯云会拉黑该地址，并不允许所有用户向该地址发送邮件。成为邮箱黑名单。如果业务方确认是误判，可以从黑名单中删除。
     * @document https://cloud.tencent.com/document/product/1288/51031
     * @param array $params [
     *              StartDate: 查询开始时间，示例：2025-10-01
     *              EndDate： 查询结束时间，示例：2025-11-01
     *              Limit： 分页参数,最大取值为100
     *              Offset： 分页参数
     *              EmailAddress： 可选参数，邮箱地址，如果传了则指定查询此邮箱
     * ]
     * @return void
     */
    public function getBlackList(array $params)
    {
        $cred = new Credential($this->secretId, $this->secretKey);

        $client = new SesClient($cred, $this->region);

        $req = new ListBlackEmailAddressRequest();

        $req->fromJsonString(json_encode($params));

        return $client->ListBlackEmailAddress($req);
    }

    /**
     * 腾讯云发送的邮件一旦被收件方判断为硬退(Hard Bounce)，腾讯云会拉黑该地址，并不允许所有用户向该地址发送邮件。成为邮箱黑名单。如果业务方确认是误判，可以从黑名单中删除。
     * @document https://cloud.tencent.com/document/product/1288/51032
     * @return void
     */
    public function deleteBlackList(array $emailList)
    {
        $cred = new Credential($this->secretId, $this->secretKey);

        $client = new SesClient($cred, $this->region);

        $req = new DeleteBlackListRequest();

        $req->fromJsonString(json_encode([
            'EmailAddressList' => $emailList
        ]));

        $resp = $client->DeleteBlackList($req);

        return !empty($resp->getRequestId());
    }
}
