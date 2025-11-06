<?php

namespace BasicHub\EsCore\Common\Classes;

use EasySwoole\Component\Singleton;

/**
 * 全局单例RSA加密类
 */
class OpensslManager extends Openssl
{
    use Singleton;
}
