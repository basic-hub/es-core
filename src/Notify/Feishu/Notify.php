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
            return $this->sendAppMessages($message);
        } else {
            return $this->sendWebHook($message);
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
        return hcurl($url, $data, 'json')->json();
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

            $result = hcurl("https://open.feishu.cn/open-apis/auth/v3/$tokenName/internal", $sendParams, 'json')->json();
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
        $result = hcurl('https://open.feishu.cn/open-apis/im/v1/images', $sendParams, 'post', $headers)->json();
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

        return hcurl($url, $sendParams, 'json', $headers)->json();
    }

    /**
     * 获取 用户、机器人、自建应用、商店应用 已加入的全部群组列表
     * @document https://open.feishu.cn/document/server-docs/group/chat/list
     * 接口频率限制 1000 次/分钟、50 次/秒；page_size 最大 100，此处自动翻页拉取全部
     * 需要开通 im:chat:readonly 或 im:chat 权限，否则返回空数组
     * @param string $userIdType open_id|union_id|user_id，仅影响返回值中 owner_id 的类型
     * @return array 群组列表，失败返回空数组
     * @throws \Exception
     */
    public function getUseList($userIdType = 'open_id')
    {
        $headers = [
            'Content-Type' => HttpClient::CONTENT_TYPE_APPLICATION_JSON,
            'Authorization' => 'Bearer ' . $this->getTenantAccessToken(),
        ];

        $list = [];
        $pageToken = '';
        do {
            $sendParams = [
                'user_id_type' => $userIdType,
                'page_size' => 100,
            ];
            if ($pageToken !== '') {
                $sendParams['page_token'] = $pageToken;
            }

            $result = hcurl('https://open.feishu.cn/open-apis/im/v1/chats', $sendParams, 'get', $headers)->json();
            if ( ! isset($result['code']) || $result['code'] != 0) {
                break;
            }

            $items = $result['data']['items'] ?? [];
            if ( ! empty($items)) {
                $list = array_merge($list, $items);
            }

            $hasMore = ! empty($result['data']['has_more']);
            $pageToken = (string)($result['data']['page_token'] ?? '');
        } while ($hasMore && $pageToken !== '');

        return $list;
    }

    /**
     * 飞书自建应用事件回调，数据解密
     * @document https://open.feishu.cn/document/event-subscription-guide/callback-subscription/step-1-choose-a-subscription-mode/send-callbacks-to-developers-server
     * 仅做解密与 verification_token 校验，url_verification 挑战、事件去重（header.event_id）由调用方处理
     * @param array $payload 飞书原始请求体，需包含 encrypt 字段
     * @return array 解密后的事件数据
     * @throws \InvalidArgumentException 未配置密钥、参数缺失或校验失败时抛出
     */
    public function appDecryptPayload(array $payload): array
    {
        $encryptKey = $this->Config->getAppEncryptKey();
        if ($encryptKey === '') {
            throw new \InvalidArgumentException('未配置飞书 encrypt_key');
        }
        $verificationToken = $this->Config->getAppVerificationToken();
        if ($verificationToken === '') {
            throw new \InvalidArgumentException('未配置飞书 Verification Token');
        }

        $encrypt = (string)($payload['encrypt'] ?? '');
        if ($encrypt === '') {
            throw new \InvalidArgumentException('请求参数缺少 encrypt，无法校验 encrypt_key');
        }

        $cipherText = base64_decode($encrypt, true);
        if ($cipherText === false || strlen($cipherText) <= 16) {
            throw new \InvalidArgumentException('飞书 encrypt 参数格式错误');
        }

        $iv = substr($cipherText, 0, 16);
        $encryptedData = substr($cipherText, 16);
        $key = hash('sha256', $encryptKey, true);
        $plainText = openssl_decrypt(
            $encryptedData,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plainText === false) {
            throw new \InvalidArgumentException('飞书 encrypt_key 校验失败');
        }

        $event = json_decode($plainText, true);
        if (!is_array($event)) {
            throw new \InvalidArgumentException('飞书解密结果不是有效的 JSON');
        }

        $eventToken = (string)($event['token'] ?? ($event['header']['token'] ?? ''));
        if ($eventToken === '' || !hash_equals($verificationToken, $eventToken)) {
            throw new \InvalidArgumentException('飞书 verification_token 校验失败');
        }

        return $event;
    }

    /**
     * 自建应用，回复指定消息
     * @document https://open.feishu.cn/document/server-docs/im-v1/message/reply
     * 接口频率限制 1000 次/分钟、50 次/秒
     * @param string $messageId 待回复的消息 id，即事件回调中的 message_id
     * @param MessageInterface $message 消息体
     * @return array|mixed|object
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function appReplyMessage(string $messageId, MessageInterface $message)
    {
        $tenantAccessToken = $this->getTenantAccessToken();
        if (empty($tenantAccessToken)) {
            throw new \InvalidArgumentException('获取飞书 tenant_access_token 失败');
        }

        $message->setInner(false);
        $sendParams = $message->fullData();

        $url = 'https://open.feishu.cn/open-apis/im/v1/messages/' . rawurlencode($messageId) . '/reply';

        $sendParams['content'] = json_encode($sendParams['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type' => HttpClient::CONTENT_TYPE_APPLICATION_JSON,
            'Authorization' => "Bearer {$tenantAccessToken}",
        ];

        return hcurl($url, $sendParams, 'json', $headers)->json();
    }
}
