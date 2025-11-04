<?php
/**
 * openssl加解密
 */


namespace BasicHub\EsCore\Common\Classes;

use EasySwoole\Component\Singleton;

/**
 * 全局单例RSA加密类
 */
class LamOpenssl extends Openssl
{
    use Singleton;
}
