<?php

namespace BasicHub\EsCore\Common\CloudLib\Sms;

/**
 * @document https://www.ccloudalarm.com/alert
 */
class CCloudAlarm extends Base
{
    protected $token;

    /**
     * 联系人手机号码、或获取组ID
     * @var string
     */
    protected $receiver = '';

    protected $title = '';

    protected $content = '';

    public function send($to = [], array $params = [])
    {
        $url = "https://www.ccloudalarm.com/api/v1/system/send_sms_alarm/?token={$this->token}";

        $params = [
            'receiver' => $this->receiver,
            'type' => 'sms',
            'title' => $this->title,
            'content' => $this->content,
        ];

        $Resp = hcurl($url, $params, 'json', ['token' => $this->token], ['throw' => false]);
        $level = $Resp->code === 200 ? 'info' : 'error';
        trace("[CCloudAlarm]params=" . var_export($params, true) . ",resp={$Resp->raw}", $level);
    }

    // 只有国内，无地区码
    public static function codeList()
    {
        return [];
    }
}
