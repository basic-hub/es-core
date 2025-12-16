<?php

namespace BasicHub\EsCore\Common\Classes;

use BasicHub\EsCore\Common\Exception\JwtException;
use BasicHub\EsCore\Common\Http\Code;
use BasicHub\EsCore\Common\Languages\Dictionary;
use EasySwoole\Jwt\Encryption;
use EasySwoole\Jwt\Exception;
use EasySwoole\Jwt\JwtObject;
use EasySwoole\Jwt\Signature;
use EasySwoole\Jwt\Jwt as EsJwt;

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
     * @return Jwt
     * @throws Exception
     */
    public function decode(string $raw): Jwt
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

    /**
     * 重写父类方法，支持自定义header和payload，修复一些问题
     * @return string
     */
    public function __toString()
    {
        $algMap = [
            EsJwt::ALG_METHOD_HMACSHA256 => EsJwt::ALG_METHOD_HS256,
            EsJwt::ALG_METHOD_AES => EsJwt::ALG_METHOD_AES,
            EsJwt::ALG_METHOD_HS256 => EsJwt::ALG_METHOD_HS256,
            EsJwt::ALG_METHOD_RS256 => EsJwt::ALG_METHOD_RS256,
        ];

        $alg = $this->getAlg();
        $alg = $algMap[$alg] ?? $alg;

        // 允许外层自定义header，此时还未经处理
        $header = $this->getHeader() ?: [
            'alg' => $alg,
            'typ' => 'JWT'
        ];
        if (is_array($header)) {
            $header = json_encode($header);
        }
        $this->header = Encryption::base64UrlEncode($header);

        // 允许外层自定义payload，此时还未经处理
        $payload = $this->getPayload() ?: [
            'exp' => $this->getExp(),
            'sub' => $this->getSub(),
            'nbf' => $this->getNbf(),
            'aud' => $this->getAud(),
            'iat' => $this->getIat(),
            'jti' => $this->getJti(),
            'iss' => $this->getIss(),
            'status' => $this->getStatus(),
            'data' => $this->getData()
        ];
        if (is_array($payload)) {
            $payload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $this->payload = Encryption::base64UrlEncode($payload);

        $this->signature = (new Signature([
            'secretKey' => $this->getSecretKey(),
            'header' => $this->header,
            'payload' => $this->payload,
            'alg' => $alg,
        ]))->__toString();

        if (empty($this->prefix)) {
            return $this->header . '.' . $this->payload . '.' . $this->signature;
        } else {
            return $this->prefix . $this->header . '.' . $this->payload . '.' . $this->signature;
        }
    }
}

