<?php

namespace BasicHub\EsCore\Common\Notify\App;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

/**
 * 企业内部应用推送消息 (receiveId) https://open.dingtalk.com/document/orgapp/asynchronous-sending-of-enterprise-session-messages
 */
class Dingtalk extends Base
{

    /**
     * 企业内部应用 appKey
     * @var string
     */
    protected $appKey = '';

    /**
     * 企业内部应用 appSecret
     * @var string
     */
    protected $appSecret = '';

    /**
     * 接收者ID类型，枚举值：userid|chatid
     * - userid: 发送工作通知给指定用户
     * - chatid: 发送消息到指定群聊（需要先把应用添加到群里）
     * @var string
     */
    protected $receiveIdType = 'userid';

    /**
     * 消息接收者ID
     * - 发送工作通知时：员工userid（多个用逗号分隔，或填"all"发送给所有人）
     * - 发送群消息时：群的openConversationId
     * @var string
     */
    protected $receiveId = '';

    /**
     * 企业内部应用agent_id
     * @var int
     */
    protected $agentId = 0;

    /**
     * 通过企业内部应用推送消息
     * 支持两种模式：
     * 1. 发送工作通知给用户 (receiveIdType=userid)
     * 2. 发送消息到群聊 (receiveIdType=chatid，需要先把应用添加到群里)
     * @document https://open.dingtalk.com/document/orgapp/asynchronous-sending-of-enterprise-session-messages
     * @param MessageInterface $message
     * @return array|mixed|object
     * @throws \Exception
     */
    public function send(MessageInterface $message)
    {
        if ($this->isdebug) {
            trace(__METHOD__ . ', message=' . var_export($message->fullData(), true));
            return true;
        }

        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            throw new \Exception('获取钉钉 access_token 失败');
        }

        $sendParams = $message->fullData();

        // 判断是发送工作通知还是群消息
        if ($this->receiveIdType === 'chatid') {
            // 发送群消息
            $url = "https://oapi.dingtalk.com/chat/send?access_token={$accessToken}";
            $params = [
                'chatid' => $this->receiveId,
                'msg' => $sendParams,
            ];
        } else {
            // 发送工作通知
            $url = "https://oapi.dingtalk.com/topapi/message/corpconversation/asyncsend_v2?access_token={$accessToken}";
            $params = [
                'agent_id' => $this->agentId,
                'msg' => $sendParams,
            ];

            // 判断是否发送给所有人，还是指定用户
            if ($this->receiveId === 'all') {
                $params['to_all_user'] = true;
            } else {
                $params['userid_list'] = $this->receiveId;
                $params['to_all_user'] = false;
            }
        }

        $result = hcurl($url, $params, 'json')->json();
        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            trace("钉钉企业应用消息发送失败: message=" . $message->__toString() . ', response=' . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
        }

        return $result;
    }


    /**
     * 获取企业内部应用 access_token
     * @document https://open.dingtalk.com/document/orgapp/obtain-orgapp-token
     * @return bool|string
     */
    public function getAccessToken()
    {
        return RedisPool::invoke(function (Redis $Redis) {
            $md5 = md5($this->appKey . $this->appSecret);
            $key = "Dingtalk-access_token-{$this->appKey}-{$md5}";

            $token = $Redis->get($key);
            if (!empty($token)) {
                return $token;
            }

            $url = 'https://oapi.dingtalk.com/gettoken';
            $params = [
                'appkey' => $this->appKey,
                'appsecret' => $this->appSecret,
            ];

            $result = hcurl($url, $params, 'get')->json();
            if (isset($result['errcode']) && $result['errcode'] == 0) {
                // 钉钉 access_token 有效期7200秒，提前5分钟刷新
                $Redis->setEx($key, 7200 - 300, $result['access_token']);
                return $result['access_token'];
            }

            trace("钉钉获取access_token失败: " . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
            return false;
        }, $this->redisPoolName);
    }

    /**
     * 获取部门用户列表
     * @document https://open.dingtalk.com/document/orgapp/queries-the-complete-information-of-a-department-user
     * @param int $deptId 部门ID，根部门传1
     * @param int $cursor 分页游标，从0开始
     * @param int $size 分页大小，最大100
     * @return array|mixed|object
     * @throws \Exception
     */
    public function getDeptUserList($deptId = 1, $cursor = 0, $size = 100)
    {
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            throw new \Exception('获取钉钉 access_token 失败');
        }

        $url = "https://oapi.dingtalk.com/topapi/v2/user/list?access_token={$accessToken}";
        $params = [
            'dept_id' => $deptId,
            'cursor' => $cursor,
            'size' => min($size, 100),
        ];

        return hcurl($url, $params, 'json')->json();
    }

    /**
     * 根据手机号获取用户userid
     * @document https://open.dingtalk.com/document/orgapp/query-users-by-phone-number
     * @param string $mobile 手机号
     * @return array|mixed|object
     * @throws \Exception
     */
    public function getUserByMobile($mobile)
    {
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            throw new \Exception('获取钉钉 access_token 失败');
        }

        $url = "https://oapi.dingtalk.com/topapi/v2/user/getbymobile?access_token={$accessToken}";
        $params = [
            'mobile' => $mobile,
        ];

        return hcurl($url, $params, 'json')->json();
    }

    /**
     * 根据userid获取用户详情
     * @document https://open.dingtalk.com/document/orgapp/query-user-details
     * @param string $userid 用户ID
     * @return array|mixed|object
     * @throws \Exception
     */
    public function getUserDetail($userid)
    {
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            throw new \Exception('获取钉钉 access_token 失败');
        }

        $url = "https://oapi.dingtalk.com/topapi/v2/user/get?access_token={$accessToken}";
        $params = [
            'userid' => $userid,
        ];

        return hcurl($url, $params, 'json')->json();
    }

    /**
     * 获取群的 openConversationId（用于发送群消息）
     * @document https://open.dingtalk.com/document/orgapp/obtain-the-chat-id-based-on-the-open-group-session
     * @param string $chatid 群ID
     * @return array|mixed|object
     * @throws \Exception
     */
    public function getOpenConversationId($chatid)
    {
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            throw new \Exception('获取钉钉 access_token 失败');
        }

        $url = "https://oapi.dingtalk.com/chat/get?access_token={$accessToken}";
        $params = [
            'chatid' => $chatid,
        ];

        return hcurl($url, $params, 'get')->json();
    }

    /**
     * 上传媒体文件到钉钉（支持图片、语音、视频、普通文件）
     * @document https://open.dingtalk.com/document/orgapp/upload-media-files
     * @param string $filePath 本地文件路径
     * @param string $type 媒体文件类型：image|voice|video|file
     * @return array|false 成功返回 ['media_id' => 'xxx', 'created_at' => 123456]，失败返回 false
     * @throws \Exception
     */
    public function uploadMedia($filePath, $type = '')
    {
        $type = $type ?: 'image';
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            throw new \Exception('获取钉钉 access_token 失败');
        }

        if (!file_exists($filePath)) {
            throw new \Exception("文件不存在: {$filePath}");
        }

        $url = "https://oapi.dingtalk.com/media/upload?access_token={$accessToken}&type={$type}";

        $params = [
            'media' => curl_file_create($filePath),
        ];

        $result = hcurl($url, $params, 'post')->json();

        if (isset($result['errcode']) && $result['errcode'] === 0) {
            return [
                'media_id' => $result['media_id'],
                'created_at' => $result['created_at'],
            ];
        }

        trace("钉钉媒体文件上传失败: filePath={$filePath}, type={$type}, response=" . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
        return false;
    }

}
