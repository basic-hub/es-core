<?php

namespace BasicHub\EsCore\Common\CloudLib\Call;

/**
 * @document https://www.ccloudalarm.com/alert
 */
class CCloudAlarm extends Base
{
    protected $token = '';

    /**
     * 号码或用户组
     * @var string
     */
    protected $receiver = '';

    protected $title = '告警电话';

    /**
     * 接听后播报的内容
     * @var string
     */
    protected $content = '';

    public function send($params = [])
    {
        $url = 'https://www.ccloudalarm.com/api/v1/system/send_phone_alarm/';

        $params = [
            'receiver' => $this->receiver,
            'type' => 'phone',
            'title' => $this->title,
            'token' => $this->token,
            'content' => $this->content,
        ];
        $Resp = hcurl($url, $params, 'json', ['token' => $this->token], ['throw' => false]);
        $level = $Resp->code === 200 ? 'info' : 'error';
        trace("[call_phone]params=" . var_export($params, true) . ",resp={$Resp->raw}", $level);
        return $Resp;
    }
}
