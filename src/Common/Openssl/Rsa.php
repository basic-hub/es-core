<?php

namespace BasicHub\EsCore\Common\Openssl;

/**
 * RSA-非对称加密
 */
class Rsa extends Base
{
    // 尽量不使用关键字，此处为与项目中配置名一致

    protected $private;

    protected $public;

    protected function initialize(): void
    {
        if ($this->private) {
            $prvResource = is_file($this->private) ? file_get_contents($this->private) : $this->private;
            $this->private = openssl_pkey_get_private($prvResource);
            if ( ! $this->private) {
                throw new \Exception('RSA私钥不可用');
            }
        }

        if ($this->public) {
            $pubResource = is_file($this->public) ? file_get_contents($this->public) : $this->public;
            $this->public = openssl_pkey_get_public($pubResource);
            if ( ! $this->public) {
                throw new \Exception('RSA公钥不可用');
            }
        }

        if (!$this->private && !$this->public) {
            throw new \Exception('缺少RSA密钥');
        }
    }

    // 公钥加密
    public function publicEncrypt($data)
    {
        return $this->encrypt($data, 'public');
    }

    // 公钥解密
    public function publicDecrypt($data)
    {
        return $this->decrypt($data, 'public');
    }

    // 私钥加密
    public function privateEncrypt($data)
    {
        return $this->encrypt($data, 'private');
    }

    // 私钥解密
    public function privateDecrypt($data)
    {
        return $this->decrypt($data, 'private');
    }

    // 加密
    protected function encrypt($data = '', $type = 'public')
    {
        $crypto = $encrypt = '';
        $func = "openssl_{$type}_encrypt";
        foreach (str_split($data, 117) as $chunk) {
            $func($chunk, $encrypt, $this->$type);
            $crypto .= $encrypt;
        }
        $crypto = $this->urlsafeB64encode($crypto);
        return $crypto;
    }

    // 解密
    protected function decrypt($data = '', $type = 'private')
    {
        $crypto = $decrypt = '';
        $func = "openssl_{$type}_decrypt";
        foreach (str_split($this->urlsafeB64decode($data), 128) as $chunk) {
            $func($chunk, $decrypt, $this->$type);
            $crypto .= $decrypt;
        }
        return $crypto;
    }

    //加密码时把特殊符号替换成URL可以带的内容
    protected function urlsafeB64encode($string)
    {
        $string = base64_encode($string);
        $string = str_replace(['+', '/', '='], ['-', '_', ''], $string);
        return $string;
    }

    //解密码时把转换后的符号替换特殊符号
    protected function urlsafeB64decode($string)
    {
        $string = str_replace(['-', '_', ' ', '\n'], ['+', '/', '+', ''], $string);
        $mod4 = strlen($string) % 4;
        if ($mod4) {
            $string .= substr('====', $mod4);
        }
        return base64_decode($string);
    }
}
