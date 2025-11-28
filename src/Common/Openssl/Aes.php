<?php

namespace BasicHub\EsCore\Common\Openssl;

/**
 * AES-对称加密
 */
class Aes extends Base
{
    /**
     * 密钥
     * @var
     */
    protected $secret;

    /**
     * 模式
     * @var string
     */
    protected $mode = 'gcm';

    /**
     * 加密算法名称
     * @var string
     */
    protected $algo = 'aes-256-gcm';

    /**
     * iv向量长度
     * @var int
     */
    protected $ivlen = 12;

    /**
     * tag长度
     * @var int
     */
    protected $taglen = 16;

    protected $block_size = 0;

    protected function initialize(): void
    {
        if (empty($this->secret)) {
            throw new \Exception('缺少AES密钥');
        }
    }

    /**
     * 数据解密
     * @param string $encrypt 密文
     * @return string
     * @throws \Exception
     */
    public function decrypt(string $encrypt)
    {
        $decoded = base64_decode($encrypt);
        if ($decoded === false) {
            throw new \Exception('数据base64解码失败');
        }

        // 提取 IV
        $iv = $this->ivlen > 0 ? substr($decoded, 0, $this->ivlen) : '';

        // 提取 Tag
        $tag = $this->taglen > 0 ? substr($decoded, -$this->taglen) : '';

        // 提取数据（将iv和tag移除）
        $ciphertext = $this->taglen > 0 ? substr($decoded, $this->ivlen, -$this->taglen) : substr($decoded, $this->ivlen);

        $decodedKey = base64_decode($this->secret);
        $options = OPENSSL_RAW_DATA;

        // 对于CBC等需要填充的模式，自动处理填充
        if (!in_array($this->mode, ['gcm', 'ecb'])) {
            $options |= OPENSSL_ZERO_PADDING;
        }

        $decrypted = openssl_decrypt(
            $ciphertext,
            $this->algo,
            $decodedKey,
            $options,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \Exception('解密失败: ' . openssl_error_string());
        }

        // 移除PKCS#7填充
        if (in_array($this->mode, ['cbc'])) {
            $pad = ord(substr($decrypted, -1));
            if ($pad > 0 && $pad <= $this->block_size) {
                $decrypted = substr($decrypted, 0, -$pad);
            }
        }

        return $decrypted;
    }

    /**
     * 数据加密
     * @param string $data
     * @return string
     * @throws \Exception
     */
    public function encrypt(string $data)
    {
        $decodedKey = base64_decode($this->secret);
        if ($decodedKey === false) {
            throw new \Exception('密钥base64解码失败');
        }

        // 生成IV（如果需要）
        $iv = $this->ivlen > 0 ? openssl_random_pseudo_bytes($this->ivlen) : '';

        $options = OPENSSL_RAW_DATA;
        $tag = '';

        // 对于CBC等模式需要进行PKCS#7填充
        if (in_array($this->mode, ['cbc'])) {
            $pad = $this->block_size - (strlen($data) % $this->block_size);
            $data .= str_repeat(chr($pad), $pad);
            $options |= OPENSSL_ZERO_PADDING;
        }

        // 执行加密
        $encrypted = openssl_encrypt(
            $data,
            $this->algo,
            $decodedKey,
            $options,
            $iv,
            $tag,
            '',
            $this->taglen
        );

        if ($encrypted === false) {
            throw new \Exception('加密失败: ' . openssl_error_string());
        }

        // 验证Tag（如果需要）
        if ($this->taglen > 0 && strlen($tag) !== $this->taglen) {
            throw new \Exception('生成的Tag长度不正确');
        }

        // 拼接数据: IV + 密文 + Tag（根据模式选择性拼接）
        $result = $iv . $encrypted;
        if ($this->taglen > 0) {
            $result .= $tag;
        }

        return base64_encode($result);
    }
}
