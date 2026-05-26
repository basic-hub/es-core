<?php

namespace BasicHub\EsCore\Common\CloudLib\Captcha;

/**
 * Cloudflare Turnstile 验证码服务端验证
 *
 * @document https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
 *
 * 客户端成功后会返回一个 token（cf-turnstile-response），
 * 服务端需将该 token 连同 secretKey 发送到 Cloudflare 进行核验。
 *
 * 用法示例（基础）:
 *   $captcha = new Cloudflare(['secretKey' => 'your_secret_key']);
 *   $result = $captcha->verify($request->getParam('cf-turnstile-response'));
 *
 * 用法示例（带幂等键，适用于可能重试的场景）:
 *   $captcha = new Cloudflare(['secretKey' => 'your_secret_key']);
 *   $result = $captcha->verify([
 *       'token'           => $request->getParam('cf-turnstile-response'),
 *       'idempotency_key' => $orderId,  // 同一业务请求使用相同的 key
 *   ]);
 */
class Cloudflare extends Base
{
    /**
     * Cloudflare Turnstile 服务端验证接口地址
     */
    const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Turnstile Secret Key（在 Cloudflare 控制台获取）
     * @var string
     */
    protected $secretKey = '';

    /**
     * 核验 Cloudflare Turnstile token
     *
     * @param string|array $verifyParam 客户端返回的 token 字符串，
     *                                  或包含以下键的数组：
     *                                    - token           string  必填，客户端 widget 返回的 token
     *                                    - idempotency_key string  可选，UUID，用于幂等重试场景。
     *                                                              同一业务请求传相同的 key，可避免
     *                                                              重试时因 token 已消费而返回失败。
     * @return bool 验证通过返回 true，失败返回 false，异常时 fail-open 返回 true
     */
    public function verify($verifyParam): bool
    {
        $token = $verifyParam['token'] ?? '';
        $idempotencyKey = $verifyParam['idempotency_key'] ?? '';

        $params = [
            'secret'   => $this->secretKey,
            'response' => $token,
            'remoteip' => $verifyParam['ip'] ?? ip(),
        ];

        // idempotency_key：可选的幂等键（UUID）。
        // Turnstile token 默认只能消费一次，重试时第二次调用会返回 success:false。
        // 传入相同的 idempotency_key 后，Cloudflare 会对相同 key 的请求返回首次结果，
        // 从而让网络超时等场景下的重试可以安全地拿到正确结论。
        if (!empty($idempotencyKey)) {
            $params['idempotency_key'] = $idempotencyKey;
        }

        try {
            $result = hcurl(self::VERIFY_URL, $params, 'post', [], [
                'htname' => 'Cloudflare:captcha'
            ]);

            // $result['success'] 为 true 表示验证通过
            return !empty($result['success']);

        } catch (\Exception $e) {
            trace('Cloudflare Turnstile 验证失败：' . $e->getMessage(), 'error');
            // 出现异常建议认为验证通过，优先保证业务可用，然后尽快排查异常原因。
            return true;
        }
    }
}
