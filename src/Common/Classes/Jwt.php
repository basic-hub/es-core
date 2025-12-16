<?php

namespace BasicHub\EsCore\Common\Classes;

use BasicHub\EsCore\Common\Exception\JwtException;
use BasicHub\EsCore\Common\Http\Code;
use BasicHub\EsCore\Common\Languages\Dictionary;
use EasySwoole\Jwt\Encryption;
use EasySwoole\Jwt\Exception;
use EasySwoole\Jwt\JwtObject;

/**
 * easyswoole/jwt组件封装不规范，在此重写
 * hash算法是计算密集型的，无需单例
 */
class Jwt extends JwtObject
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public function getToken(): string
    {
        return $this->__toString();
    }

    /**
     * 验证和解析jwt
     * @param string $token
     * @return Jwt
     */
    public function verifyToken($token): Jwt
    {
        try {

            // 兼容早期非jwt标准格式 todo 后面可能删除此兼容代码 - 2025-12-16
            if (count(explode('.', $token)) !== 3) {
                $token = base64_decode($token);
            }

            $Jwt = $this->decode($token);
            $status = $Jwt->getStatus();

            switch ($status) {
                case Jwt::STATUS_OK:
                    return $Jwt;
                case Jwt::STATUS_SIGNATURE_ERROR:
                    throw new JwtException(lang(Dictionary::JWT_INVALID), Code::JWT_INVALID);
                case Jwt::STATUS_EXPIRED:
                    throw new JwtException(lang(Dictionary::JWT_EXPIRED), Code::JWT_EXPIRED);
                default:
                    throw new JwtException('Other Error', Code::JWT_OTHER);
            }
        } catch (\Exception $e) {
            throw new JwtException($e->getMessage(), Code::JWT_OTHER, $e);
        }
    }

    /**
     * @return self
     * @throws Exception
     */
    public function decode(string $raw)
    {
        if (strpos($raw, ' ')) {
            $prefix       = explode(' ', $raw);
            $this->prefix = $prefix[0];
            $raw          = str_replace($this->prefix . ' ', '', $raw);
        }

        $items = explode('.', $raw);

        // token格式
        if (count($items) !== 3) {
            throw new Exception('Token format error!');
        }

        // 验证header
        $header = Encryption::base64UrlDecode($items[0]);
        $header = json_decode($header, true);
        if (empty($header)) {
            throw new Exception('Token header is empty!');
        }

        // 验证payload
        $payload = Encryption::base64UrlDecode($items[1]);
        $payload = json_decode($payload, true);
        if (empty($payload)) {
            throw new Exception('Token payload is empty!');
        }

        if (empty($items[2])) {
            throw new Exception('Signature is empty!');
        }

        $jwtObjConfig = array_merge(
            $header,
            $payload,
            [
                'header' => $items[0],
                'payload' => $items[1],
                'signature' => $items[2],
                'secretKey' => $this->secretKey,
                'alg' => $this->alg
            ],
            ['prefix' => $this->prefix]
        );

        return new static($jwtObjConfig);
    }

}

