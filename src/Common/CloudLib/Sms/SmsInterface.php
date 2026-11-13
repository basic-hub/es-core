<?php

namespace BasicHub\EsCore\Common\CloudLib\Sms;

/**
 * 发送短信
 */
interface SmsInterface
{
    /**
     * 发送短信
     * @param string|number|array $to 发送的号码
     * @param array $params 短信模板参数，请不要传递额外参数
     * @return mixed
     */
    function send($to = [], array $params = []);

    /**
     * 国际短信必选地区码，不同云商支持的地区不同，由子类根据云商接口实现
     * @return array
     */
    static function codeList();
}
