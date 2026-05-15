<?php

namespace BasicHub\EsCore\Notify\Feishu;

use EasySwoole\HttpClient\HttpClient;
use BasicHub\EsCore\Notify\Interfaces\ConfigInterface;
use BasicHub\EsCore\Notify\Interfaces\MessageInterface;
use BasicHub\EsCore\Notify\Interfaces\NotifyInterface;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

class Notify implements NotifyInterface
{
    /**
     * @var Config
     */
    protected $Config = null;

    public function __construct(ConfigInterface $Config)
    {
        $this->Config = $Config;
    }


    public function does(MessageInterface $message)
    {
        // 优先使用自建应用推送，实例还需要应用appid、secret等参数，这里暂不做校验
        if ($this->Config->getReceiveId()) {
            $this->sendAppMessages($message);
        } else {
            $this->sendWebHook($message);
        }
    }

    /**
     * @document https://open.feishu.cn/document/client-docs/bot-v3/add-custom-bot#%E6%94%AF%E6%8C%81%E5%8F%91%E9%80%81%E7%9A%84%E6%B6%88%E6%81%AF%E7%B1%BB%E5%9E%8B%E8%AF%B4%E6%98%8E
     * 自定义机器人的频率控制和普通应用不同，为 100 次/分钟，5 次/秒
     * @param MessageInterface $message
     * @return void|array
     */
    public function sendWebHook(MessageInterface $message)
    {
        $data = $message->fullData();

        $url = $this->Config->getUrl();
        $secret = $this->Config->getSignKey();

        $timestamp = time();

        $sign = base64_encode(hash_hmac('sha256', '', $timestamp . "\n" . $secret, true));

        $data['timestamp'] = $timestamp;
        $data['sign'] = $sign;

        // 支持文本(text)、富文本(textarea)、群名片(share_chat)、图片(image)、消息卡片(interactive)消息类型
        return hcurl($url, $data, 'json');
    }

    /**
     * @param string $tokenName tenant_access_token|app_access_token
     * @return bool|string
     */
    protected function getAccessToken($tokenName)
    {
        $Config = $this->Config;
        return RedisPool::invoke(function (Redis $Redis) use ($Config, $tokenName) {
            $appId = $Config->getAppId();
            $appSecret = $Config->getAppSecret();
            // 再拼接md5值，任何一个参数变化，都重新缓存。否则重置密钥之后会有问题。明文appid是给人看
            $md5 = md5($appId . $appSecret);
            $key = "Feishu-{$tokenName}-{$appId}-{$md5}";

            $token = $Redis->get($key);
            // 命中redis
            if ( ! empty($token)) {
                return $token;
            }

            $sendParams = [
                'app_id' => $appId,
                'app_secret' => $appSecret,
            ];

            $result = hcurl("https://open.feishu.cn/open-apis/auth/v3/$tokenName/internal", $sendParams, 'json');
            if (isset($result['code']) && $result['code'] == 0) {
                $Redis->setEx($key, $result['expire'] - 60, $result[$tokenName]);
                return $result[$tokenName];
            }
            return false;
        }, $Config->getRedisPoolName());
    }

    /**
     * 自建应用获取 tenant_access_token
     * @document https://open.feishu.cn/document/server-docs/authentication-management/access-token/tenant_access_token_internal
     * @return bool|string
     */
    public function getTenantAccessToken()
    {
        return $this->getAccessToken('tenant_access_token');
    }

    /**
     * 自建应用获取 app_access_token
     * @document https://open.feishu.cn/document/server-docs/authentication-management/access-token/app_access_token_internal
     * @return bool|string
     */
    public function getAppAccessToken()
    {
        return $this->getAccessToken('app_access_token');
    }

    /**
     * 上传图片至飞书
     * @document https://open.feishu.cn/document/server-docs/im-v1/image/create?appId=cli_a6f0289db033500b
     * @param string $img
     * @return mixed|string
     * @throws \Exception
     */
    public function uploadImg($img)
    {
        $tenant_access_token = $this->getTenantAccessToken();
        $headers = [
            'Content-Type' => HttpClient::CONTENT_TYPE_FORM_DATA,
            'Authorization' => "Bearer {$tenant_access_token}",
        ];
        $sendParams = [
            'image_type' => 'message',
            'image' => curl_file_create($img),
        ];
        $result = hcurl('https://open.feishu.cn/open-apis/im/v1/images', $sendParams, 'post', $headers);
        if (isset($result['code']) && $result['code'] == 0) {
            return $result['data']['image_key'];
        } else {
            return '';
        }
    }

    /**
     * 通过自建应用|商店应用推送飞书消息
     * @document https://open.feishu.cn/document/server-docs/im-v1/message/create?appId=cli_a6f0289db033500b
     * 接口频率限制 1000 次/分钟、50 次/秒
     * @param MessageInterface $message
     * @return array|mixed|object
     * @throws \Exception
     */
    public function sendAppMessages(MessageInterface $message)
    {
        $message->setInner(false);
        $sendParams = $message->fullData();

        $receiveIdType = $this->Config->getReceiveIdType();
        $receiveId = $this->Config->getReceiveId();

        $url = "https://open.feishu.cn/open-apis/im/v1/messages?receive_id_type=$receiveIdType";
        $headers = [
            'Content-Type' => HttpClient::CONTENT_TYPE_APPLICATION_JSON,
            'Authorization' => 'Bearer ' . $this->getTenantAccessToken(),
        ];
        $sendParams['receive_id'] = $receiveId;
        $sendParams['content'] = json_encode($sendParams['card'] ?? $sendParams['content']); // 实际上要二次encode,下面还有一次

        return hcurl($url, $sendParams, 'json', $headers);
    }
}
