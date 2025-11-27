<?php

namespace BasicHub\EsCore\HttpController\Api;

use BasicHub\EsCore\Common\Openssl\RsaManager;

trait BaseTrait
{
    protected $rsa = [];

    protected function onRequest(?string $action): ?bool
    {
        return parent::onRequest($action)
            && ($this->input['encry'] == 'md5' ? $this->_checkMd5Sign() : $this->_checkRsaSign());
    }

    protected function _checkRsaSign()
    {
        $secret = $this->input[config('RSA.key')];
        if ( ! $secret) {
            return false;
        }
        $data = RsaManager::getInstance()->privateDecrypt($secret);
        $this->rsa = json_decode($data, true);
        return is_array($this->rsa);
    }

    protected function _checkMd5Sign()
    {
        $this->rsa = $this->input;
        return self_sign($this->input, $this->input['sign']);
    }
}
