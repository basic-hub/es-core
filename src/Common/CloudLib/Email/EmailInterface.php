<?php

namespace BasicHub\EsCore\Common\CloudLib\Email;

/**
 * 发送邮件
 */
interface EmailInterface
{
    /**
     * @param string|array $to 要发送的邮箱
     * @param array $params 模板参数，请不要传递额外参数
     * @return mixed
     */
    function send($to = [], array $params = []);
}
