<?php

namespace BasicHub\EsCore\Common\Classes;

/**
 * 可动态实例化RSA加密类
 */
class Openssl
{
    /**
     * @var resource 私钥类
     */
    private $_privateKey;

    /**
     * @var resource 公钥类
     */
    private $_publicKey;

    public function __construct($prvfile, $pubfile)
    {
        if ( ! extension_loaded('openssl')) {
            throw new \RuntimeException('php需要openssl扩展支持');
        }

        if ($prvfile) {
            $prvResource = is_file($prvfile) ? file_get_contents($prvfile) : $prvfile;
            $this->_privateKey = openssl_pkey_get_private($prvResource);
            if ( ! $this->_privateKey) {
                throw new \RuntimeException('Openssl私钥不可用');
            }
        }
        if ($pubfile) {
            $pubResource = is_file($pubfile) ? file_get_contents($pubfile) : $pubfile;
            $this->_publicKey = openssl_pkey_get_public($pubResource);
            if ( ! $this->_publicKey) {
                throw new \RuntimeException('Openssl公钥不可用');
            }
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
    public function encrypt($data = '', $type = 'public')
    {
        $crypto = $encrypt = '';
        $func = "openssl_{$type}_encrypt";
        $key = "_{$type}Key";
        foreach (str_split($data, 117) as $chunk) {
            $func($chunk, $encrypt, $this->$key);
            $crypto .= $encrypt;
        }
        $crypto = $this->urlsafeB64encode($crypto);
        return $crypto;
    }

    // 解密
    public function decrypt($data = '', $type = 'private')
    {
        $crypto = $decrypt = '';
        $func = "openssl_{$type}_decrypt";
        $key = "_{$type}Key";
        foreach (str_split($this->urlsafeB64decode($data), 128) as $chunk) {
            $func ($chunk, $decrypt, $this->$key);
            $crypto .= $decrypt;
        }
        return $crypto;
    }

    //加密码时把特殊符号替换成URL可以带的内容
    public function urlsafeB64encode($string)
    {
        $string = base64_encode($string);
        $string = str_replace(['+', '/', '='], ['-', '_', ''], $string);
        return $string;
    }

    //解密码时把转换后的符号替换特殊符号
    public function urlsafeB64decode($string)
    {
        $string = str_replace(['-', '_', ' ', '\n'], ['+', '/', '+', ''], $string);
        $mod4 = strlen($string) % 4;
        if ($mod4) {
            $string .= substr('====', $mod4);
        }
        return base64_decode($string);
    }

    /**
     * AES-GCM 数据解密
     * AES加密不同模式前置处理各不相同，不一一兼容，此处仅GCM模式
     * @param string $encrypt 密文
     * @param string $secretKey  密钥
     * @param string $cipherAlgo 算法名
     * @param int $ivLength iv向量长度
     * @param int $tagLength tag长度
     * @return string
     * @throws \Exception
     */
    public static function aesGcmDecrypt(string $encrypt, string $secretKey, $cipherAlgo = 'aes-256-gcm', $ivLength = 12, $tagLength = 16)
    {
        // base64得到字节序
        $decoded = base64_decode($encrypt);
        if ($decoded === false) {
            throw new \Exception('数据base64解码失败');
        }

        // 提取 IV
        $ivDecrypt = substr($decoded, 0, $ivLength);

        // 提取 Tag
        $tag = substr($decoded, -$tagLength);

        // 提取数据（将iv和tag移除）
        $ciphertext = substr($decoded, $ivLength, -$tagLength);

        $decodedKey = base64_decode($secretKey);
        if ($decodedKey === false) {
            throw new \Exception('密钥base64解码失败');
        }

        $decrypted = openssl_decrypt(
            $ciphertext,
            $cipherAlgo,
            $decodedKey,
            OPENSSL_RAW_DATA,
            $ivDecrypt,
            $tag
        );

        if ($decrypted === false) {
            throw new \Exception('Decryption failed: ' . openssl_error_string());
        }

        // Return response as UTF-8 string
        return $decrypted;
    }

    /**
     * AES-GCM 数据加密
     * AES加密不同模式前置处理各不相同，不一一兼容，此处仅GCM模式
     * @param string $data
     * @param string $secretKey
     * @param string $cipherAlgo
     * @param int $ivLength
     * @param int $tagLength
     * @return string
     * @throws \Exception
     */
    public static function aesGcmEncrypt(string $data, string $secretKey, $cipherAlgo = 'aes-256-gcm', $ivLength = 12, $tagLength = 16)
    {
        // 解码Base64格式的密钥
        $decodedKey = base64_decode($secretKey);
        if ($decodedKey === false) {
            throw new \Exception('密钥base64解码失败');
        }

        // 生成指定长度的随机IV（GCM模式推荐12字节IV）唯一
        $iv = openssl_random_pseudo_bytes($ivLength);

        // 执行AES-GCM加密（会自动生成Tag）
        $encrypted = openssl_encrypt(
            $data,
            $cipherAlgo,
            $decodedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag, // 引用传递，用于接收生成的Tag
            '',
            $tagLength
        );

        if ($encrypted === false) {
            throw new \Exception('Encryption failed: ' . openssl_error_string());
        }

        // 验证Tag长度是否符合预期（防止异常情况）
        if (strlen($tag) !== $tagLength) {
            throw new \Exception('TAG长度不一致');
        }

        // 拼接IV + 密文 + Tag，然后进行Base64编码
        $result = $iv . $encrypted . $tag;
        return base64_encode($result);
    }
}
