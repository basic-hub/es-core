<?php

namespace BasicHub\EsCore\Common\CloudLib\Notify;

use EasySwoole\HttpClient\HttpClient;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

/**
 * 飞书通知驱动
 * 支持两种方式：
 * 1. 自定义webhook机器人 （url+signKey）
 * 2. 自建应用推送消息 (receiveId) https://open.feishu.cn/document/server-docs/im-v1/message/create
 */
class Feishu extends Base
{
    /**
     * WebHook参数，url
     * @var string
     */
    protected $url = '';

    /**
     * webhook参数，密钥
     * @var string
     */
    protected $signKey = '';

    /**
     * 要@哪些人（Open ID 或 User ID）, true-所有人,false-谁也不鸟
     * @var array|bool
     */
    protected $at = false;

    /**
     * 自建应用发送时参数，发送的消息类型，枚举值：open_id|union_id|user_id|email|chat_id
     * @var string
     */
    protected $receiveIdType = 'chat_id';

    /**
     * 自建应用发送时参数，消息接收者ID
     * @var string
     */
    protected $receiveId = '';

    /**
     * 自建应用appId
     * @var string
     */
    protected $appId = '';

    /**
     * 自建应用密钥
     * @var string
     */
    protected $appSecret = '';

    /**
     * 自建应用，事件回调加密key
     * @var string
     */
    protected $appEncryptKey = '';

    /**
     * 自建应用，事件回调校验token
     * @var string
     */
    protected $appVerificationToken = '';

    /**
     * 自建应用获取tenant_access_token时，需要redis缓存
     * @var string
     */
    protected $redisPoolName = 'admin';

    /**
     * 实际发送消息
     * @param MessageInterface $message
     * @return array
     * @throws \Exception
     */
    public function send(MessageInterface $message)
    {
        if ($this->isdebug) {
            trace(__METHOD__ . ', message=' . var_export($message->fullData(), true));
            return true;
        }

        // 优先使用自建应用推送
        if ($this->receiveId) {
            $result = $this->sendAppMessages($message);
        } else {
            $result = $this->sendWebHook($message);
        }

        // 判断是否发送成功
        return $result;
    }

    /**
     * @document https://open.feishu.cn/document/client-docs/bot-v3/add-custom-bot
     * 自定义机器人的频率控制：100 次/分钟，5 次/秒
     * @param MessageInterface $message
     * @return array|mixed|object
     */
    public function sendWebHook(MessageInterface $message)
    {
        $data = $message->fullData();

        $timestamp = time();
        $sign = base64_encode(hash_hmac('sha256', '', $timestamp . "\n" . $this->signKey, true));

        $data['timestamp'] = $timestamp;
        $data['sign'] = $sign;

        return hcurl($this->url, $data, 'json')->json();
    }

    /**
     * 通过自建应用推送飞书消息
     * @document https://open.feishu.cn/document/server-docs/im-v1/message/create
     * 接口频率限制 1000 次/分钟、50 次/秒
     * @param MessageInterface $message
     * @return array|mixed|object
     * @throws \Exception
     */
    public function sendAppMessages(MessageInterface $message)
    {
        $sendParams = $message->fullData();

        $url = "https://open.feishu.cn/open-apis/im/v1/messages?receive_id_type={$this->receiveIdType}";
        $headers = [
            'Content-Type' => HttpClient::CONTENT_TYPE_APPLICATION_JSON,
            'Authorization' => 'Bearer ' . $this->getTenantAccessToken(),
        ];
        $sendParams['receive_id'] = $this->receiveId;
        $sendParams['content'] = json_encode($sendParams['card'] ?? $sendParams['content']);

        return hcurl($url, $sendParams, 'json', $headers)->json();
    }

    /**
     * @param string $tokenName tenant_access_token|app_access_token
     * @return bool|string
     */
    protected function getAccessToken($tokenName)
    {
        return RedisPool::invoke(function (Redis $Redis) use ($tokenName) {
            $md5 = md5($this->appId . $this->appSecret);
            $key = "Feishu-{$tokenName}-{$this->appId}-{$md5}";

            $token = $Redis->get($key);
            if (!empty($token)) {
                return $token;
            }

            $sendParams = [
                'app_id' => $this->appId,
                'app_secret' => $this->appSecret,
            ];

            $result = hcurl("https://open.feishu.cn/open-apis/auth/v3/$tokenName/internal", $sendParams, 'json')->json();
            if (isset($result['code']) && $result['code'] == 0) {
                $Redis->setEx($key, $result['expire'] - 60, $result[$tokenName]);
                return $result[$tokenName];
            }
            return false;
        }, $this->redisPoolName);
    }

    /**
     * 自建应用获取 tenant_access_token
     * @document https://open.feishu.cn/document/server-docs/authentication-management/access-token/tenant_access_token_internal
     */
    public function getTenantAccessToken()
    {
        return $this->getAccessToken('tenant_access_token');
    }

    /**
     * 自建应用获取 app_access_token
     * @document https://open.feishu.cn/document/server-docs/authentication-management/access-token/app_access_token_internal
     */
    public function getAppAccessToken()
    {
        return $this->getAccessToken('app_access_token');
    }

    /**
     * 上传媒体文件到飞书（目前仅支持图片）
     * @document https://open.feishu.cn/document/server-docs/im-v1/image/create
     * @param string $filePath 本地文件路径
     * @param string $type 图片类型：message（消息图片）|avatar（头像）
     * @return string|false 成功返回 image_key，失败返回 false
     * @throws \Exception
     */
    public function uploadMedia($filePath, $type = 'message')
    {
        $tenant_access_token = $this->getTenantAccessToken();
        if (empty($tenant_access_token)) {
            throw new \Exception('获取飞书 tenant_access_token 失败');
        }

        if (!file_exists($filePath)) {
            throw new \Exception("文件不存在: {$filePath}");
        }

        $headers = [
            'Content-Type' => HttpClient::CONTENT_TYPE_FORM_DATA,
            'Authorization' => "Bearer {$tenant_access_token}",
        ];
        $sendParams = [
            'image_type' => $type,
            'image' => curl_file_create($filePath),
        ];
        $result = hcurl('https://open.feishu.cn/open-apis/im/v1/images', $sendParams, 'post', $headers)->json();
        
        if (isset($result['code']) && $result['code'] == 0) {
            return $result['data']['image_key'];
        }

        trace("飞书媒体文件上传失败: filePath={$filePath}, type={$type}, response=" . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
        return false;
    }

    /**
     * 获取已加入的全部群组列表
     * @document https://open.feishu.cn/document/server-docs/group/chat/list
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
            if (!isset($result['code']) || $result['code'] != 0) {
                break;
            }

            $items = $result['data']['items'] ?? [];
            if (!empty($items)) {
                $list = array_merge($list, $items);
            }

            $hasMore = !empty($result['data']['has_more']);
            $pageToken = (string)($result['data']['page_token'] ?? '');
        } while ($hasMore && $pageToken !== '');

        return $list;
    }

    /**
     * 飞书自建应用事件回调，数据解密
     * @document https://open.feishu.cn/document/event-subscription-guide/callback-subscription/step-1-choose-a-subscription-mode/send-callbacks-to-developers-server
     */
    public function appDecryptPayload(array $payload): array
    {
        if ($this->appEncryptKey === '') {
            throw new \InvalidArgumentException('未配置飞书 encrypt_key');
        }
        if ($this->appVerificationToken === '') {
            throw new \InvalidArgumentException('未配置飞书 Verification Token');
        }

        $encrypt = (string)($payload['encrypt'] ?? '');
        if ($encrypt === '') {
            throw new \InvalidArgumentException('请求参数缺少 encrypt');
        }

        $cipherText = base64_decode($encrypt, true);
        if ($cipherText === false || strlen($cipherText) <= 16) {
            throw new \InvalidArgumentException('飞书 encrypt 参数格式错误');
        }

        $iv = substr($cipherText, 0, 16);
        $encryptedData = substr($cipherText, 16);
        $key = hash('sha256', $this->appEncryptKey, true);
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
        if ($eventToken === '' || !hash_equals($this->appVerificationToken, $eventToken)) {
            throw new \InvalidArgumentException('飞书 verification_token 校验失败');
        }

        return $event;
    }

    /**
     * 自建应用，回复指定消息
     * @document https://open.feishu.cn/document/server-docs/im-v1/message/reply
     */
    public function appReplyMessage(string $messageId, MessageInterface $message)
    {
        $tenantAccessToken = $this->getTenantAccessToken();
        if (empty($tenantAccessToken)) {
            throw new \InvalidArgumentException('获取飞书 tenant_access_token 失败');
        }

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
