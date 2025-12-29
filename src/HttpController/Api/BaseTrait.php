<?php

namespace BasicHub\EsCore\HttpController\Api;

use BasicHub\EsCore\HttpController\BaseControllerTrait;

/**
 * @mixin BaseControllerTrait
 */
trait BaseTrait
{
    protected function onRequest(?string $action): ?bool
    {
        $return = parent::onRequest($action);
        if (!$return) {
            return $return;
        }

        if ($this->input['encry'] == 'md5') {
            return $this->_checkMd5Sign();
        } else {
            return $this->_checkRsaSign();
        }
    }

    protected function _checkRsaSign()
    {
        // 启用RSA加密
        $this->rsacfg = ['open' => true] + config('RSA');
        $this->decodeRsa();
        // 请求参数二次处理
        $this->requestParams();
        return !empty($this->rsa);
    }

    protected function _checkMd5Sign()
    {
        $this->rsa = $this->input;
        return self_sign($this->input, $this->input['sign']);
    }
}
