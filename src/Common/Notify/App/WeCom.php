<?php

namespace BasicHub\EsCore\Common\Notify\App;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

/**
 * 企业应用推送消息 (corpId + corpSecret + agentId)
 */
class WeCom extends Base
{

    /**
     * 企业ID
     * @var string
     */
    protected $corpId = '';

    /**
     * 应用的凭证密钥
     * @var string
     */
    protected $corpSecret = '';

    /**
     * 企业应用的 AgentId
     * @var int
     */
    protected $agentId = 0;

    /**
     * 接收者ID类型，枚举值：userid|chatid
     * - userid: 发送给指定成员
     * - chatid: 发送到指定群聊
     * @var string
     */
    protected $receiveIdType = 'userid';

    /**
     * 消息接收者ID
     * - userid 模式：成员ID，多个用|分隔，或填"@all"发送给全部成员
     * - chatid 模式：群聊ID
     * @var string
     */
    protected $receiveId = '';

    /**
     * 通过企业应用推送消息
     * @document https://developer.work.weixin.qq.com/document/path/90236
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
            throw new \Exception('获取企业微信 access_token 失败');
        }

        $sendParams = $message->fullData();
        $url = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token={$accessToken}";

        $params = [
            'agentid' => $this->agentId,
        ];

        // 判断是发送给用户还是发送到群聊
        if ($this->receiveIdType === 'chatid') {
            // 发送到群聊（应用需要先加入群聊）
            $params['chatid'] = $this->receiveId;
        } else {
            // 发送给指定成员
            $params['touser'] = $this->receiveId;
        }

        // 合并消息内容
        $params = array_merge($params, $sendParams);

        $result = hcurl($url, $params, 'json')->json();

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            trace("企业微信应用消息发送失败: message=" . $message->__toString() . ', response=' . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
        }

        return $result;
    }

    /**
     * 获取企业应用 access_token
     * @document https://developer.work.weixin.qq.com/document/path/91039
     * @return bool|string
     */
    public function getAccessToken()
    {
        return RedisPool::invoke(function (Redis $Redis) {
            $md5 = md5($this->corpId . $this->corpSecret);
            $key = "WeCom-access_token-{$this->corpId}-{$md5}";

            $token = $Redis->get($key);
            if (!empty($token)) {
                return $token;
            }

            $url = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken';
            $params = [
                'corpid' => $this->corpId,
                'corpsecret' => $this->corpSecret,
            ];

            $result = hcurl($url, $params, 'get')->json();
            if (isset($result['errcode']) && $result['errcode'] == 0) {
                // access_token 有效期7200秒，提前5分钟刷新
                $Redis->setEx($key, 7200 - 300, $result['access_token']);
                return $result['access_token'];
            }

            trace("企业微信获取 access_token 失败: " . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
            return false;
        }, $this->redisPoolName);
    }

    /**
     * 上传媒体文件到企业微信（支持图片、语音、视频、普通文件）
     * @document https://developer.work.weixin.qq.com/document/path/90253
     * @param string $filePath 本地文件路径
     * @param string $type 媒体文件类型：image|voice|video|file
     * @return array|false 成功返回 ['media_id' => 'xxx', 'created_at' => 123456, 'type' => 'image']，失败返回 false
     * @throws \Exception
     */
    public function uploadMedia($filePath, $type = '')
    {
        $type = $type ?: 'image';
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            throw new \Exception('获取企业微信 access_token 失败');
        }

        if (!file_exists($filePath)) {
            throw new \Exception("文件不存在: {$filePath}");
        }

        $url = "https://qyapi.weixin.qq.com/cgi-bin/media/upload?access_token={$accessToken}&type={$type}";

        $params = [
            'media' => curl_file_create($filePath),
        ];

        $result = hcurl($url, $params, 'post')->json();

        if (isset($result['errcode']) && $result['errcode'] === 0) {
            return [
                'media_id' => $result['media_id'],
                'created_at' => $result['created_at'],
                'type' => $result['type'],
            ];
        }

        trace("企业微信媒体文件上传失败: filePath={$filePath}, type={$type}, response=" . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
        return false;
    }

    /**
     * 获取部门成员列表
     * @document https://developer.work.weixin.qq.com/document/path/90200
     * @param int $departmentId 部门ID，根部门传1
     * @param bool $fetchChild 是否递归获取子部门成员
     * @return array|mixed|object
     * @throws \Exception
     */
    public function getDepartmentUsers($departmentId = 1, $fetchChild = false)
    {
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            throw new \Exception('获取企业微信 access_token 失败');
        }

        $url = "https://qyapi.weixin.qq.com/cgi-bin/user/simplelist?access_token={$accessToken}";
        $params = [
            'department_id' => $departmentId,
            'fetch_child' => $fetchChild ? 1 : 0,
        ];

        return hcurl($url, $params, 'get')->json();
    }

    /**
     * 根据手机号获取成员 userid
     * @document https://developer.work.weixin.qq.com/document/path/91693
     * @param string $mobile 手机号
     * @return array|mixed|object
     * @throws \Exception
     */
    public function getUserByMobile($mobile)
    {
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            throw new \Exception('获取企业微信 access_token 失败');
        }

        $url = "https://qyapi.weixin.qq.com/cgi-bin/user/getuserid?access_token={$accessToken}";
        $params = [
            'mobile' => $mobile,
        ];

        return hcurl($url, $params, 'json')->json();
    }

    /**
     * 根据 userid 获取成员详情
     * @document https://developer.work.weixin.qq.com/document/path/90196
     * @param string $userid 成员 UserID
     * @return array|mixed|object
     * @throws \Exception
     */
    public function getUserDetail($userid)
    {
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            throw new \Exception('获取企业微信 access_token 失败');
        }

        $url = "https://qyapi.weixin.qq.com/cgi-bin/user/get?access_token={$accessToken}";
        $params = [
            'userid' => $userid,
        ];

        return hcurl($url, $params, 'get')->json();
    }

    /**
     * 获取群聊详情
     * @document https://developer.work.weixin.qq.com/document/path/90245
     * @param string $chatid 群聊ID
     * @return array|mixed|object
     * @throws \Exception
     */
    public function getChatInfo($chatid)
    {
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            throw new \Exception('获取企业微信 access_token 失败');
        }

        $url = "https://qyapi.weixin.qq.com/cgi-bin/appchat/get?access_token={$accessToken}";
        $params = [
            'chatid' => $chatid,
        ];

        return hcurl($url, $params, 'get')->json();
    }

}

