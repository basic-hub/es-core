<?php

namespace BasicHub\EsCore\Common\Classes;

use BasicHub\EsCore\Common\Exception\JwtException;
use BasicHub\EsCore\Common\Http\Code;
use BasicHub\EsCore\Common\Languages\Dictionary;
use EasySwoole\Jwt\Jwt;
use EasySwoole\Jwt\JwtObject;

class LamJwt
{
    /**
     * 生成jwt
     * @param array $data jwt数据
     * @param string $key jwt密钥
     * @param int $expire 有效期，秒
     * @param \Closure|null $call 自定义jwt处理  function (JwtObject $jwt) {}
     * @return string jwt值
     */
    public static function getToken($data = [], $secret = '', int $expire = 0, \Closure $call = null)
    {
        $time = time();
        $uniqid = uniqid();

        $jwt = Jwt::getInstance()
            ->setSecretKey($secret) // 秘钥
            ->publish();

        $jwt->setAlg('HMACSHA256'); // 加密方式
        $jwt->setExp($time + $expire); // 过期时间
        $jwt->setIat($time); // 发布时间
        $jwt->setJti($uniqid); // jwt id 用于标识该jwt
        //$jwt->setNbf(time()+60*5); // 在此之前不可用
        //$jwt->setSub($sub ?? ''); // 主题
        //$jwt->setAud($aud ?? ''); // 用户(接收jwt的一方)
        //$jwt->setIss($iss ?? ''); // 发行人(jwt签发者)

        // 自定义数据
        $jwt->setData($data);

        if ($call && is_callable($call)) {
            $call($jwt);
        }

        // 最终生成的token
        $token = $jwt->__toString();
        return base64_encode($token);
    }

    /**
     * 验证和解析jwt
     * @param string $token
     * @param string $key
     * @param bool $only_data 是否只返回data字段的内容
     * @return array status为1才代表成功
     */
    public static function verifyToken($token, $key = '')
    {
        $token = base64_decode($token);
        try {
            $secret = $key ?: config('ENCRYPT.key');
            $jwt = Jwt::getInstance()->setSecretKey($secret)->decode($token);
            $status = $jwt->getStatus();

            switch ($status) {
                case  1:
                    $data = [
                        'aud' => $jwt->getAud(),
                        'data' => $jwt->getData(),
                        'exp' => $jwt->getExp(),
                        'iat' => $jwt->getIat(),
                        'iss' => $jwt->getIss(),
                        'jti' => $jwt->getJti(),
                        'sub' => $jwt->getSub()
                    ];
                    return $data;
                case  -1:
                    throw new JwtException(lang(Dictionary::JWT_INVALID), Code::JWT_INVALID);
                case  -2:
                    throw new JwtException(lang(Dictionary::JWT_EXPIRED), Code::JWT_EXPIRED);
            }
        } catch (\Exception $e) {
            throw new JwtException($e->getMessage(), Code::JWT_OTHER, $e);
        }
    }
}
