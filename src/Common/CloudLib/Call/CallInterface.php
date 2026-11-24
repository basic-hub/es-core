<?php

namespace BasicHub\EsCore\Common\CloudLib\Call;

/**
 * 拨打电话
 */
interface CallInterface
{
    /**
     * 拨打指定用户组电话
     * @param $params
     * @return mixed
     */
    public function send($params = []);
}
