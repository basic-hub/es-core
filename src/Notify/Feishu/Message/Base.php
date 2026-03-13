<?php

namespace BasicHub\EsCore\Notify\Feishu\Message;

use EasySwoole\HttpClient\HttpClient;
use EasySwoole\Spl\SplBean;
use BasicHub\EsCore\Notify\Interfaces\MessageInterface;

abstract class Base extends SplBean implements MessageInterface
{
    /**
     * OpenID
     * @var array
     */
    protected $atOpenID = [];

    /**
     * UserID
     * @var array
     */
    protected $atUserID = [];

    /**
     * 是否使用内部消息格式
     * @var boolean
     */
    protected $inner = true;

    /**
     * 主标题
     * @var string
     */
    protected $title = '程序异常';

    /**
     * 内容
     * @var string
     */
    protected $content = '';

    protected $isAtAll = false;

    /**
     * at 富文本语法
     * @return mixed|string
     */
    public function getAtText()
    {
        $at = '';

        // @所有人
        if ($this->isAtAll) {
            $at .= '<at user_id="all">所有人</at>';
        } elseif (!empty($this->atUserID)) {
            // @ 指定人员
            foreach ($this->atUserID as $id => $name) {
                $at .= '<at user_id="' . $id . '">' . $name . '</at>';
            }
        }

        return $at;
    }

    /**
     * at 模板语法
     * @return array|array[]
     */
    public function getAtTpl()
    {
        $at = [];
        // @所有人
        if ($this->isAtAll) {
            $at = [
                [
                    'tag' => 'at',
                    'user_id' => 'all',
                    'user_name' => '所有人',
                ]
            ];
        } elseif (!empty($this->atUserID)) {
            // @ 指定人员
            foreach ($this->atUserID as $id => $name) {
                $at[] = [
                    'tag' => 'at',
                    'user_id' => $id,
                    'user_name' => $name,
                ];
            }
        }

        return $at;
    }

    public function getServerText()
    {
        return PHP_EOL . implode(PHP_EOL, [
                '系统：' . APP_MODULE,
                '服务器：' . config('SERVNAME'),
                '时间：' . date('Y年m月d日 H:i:s')
            ]);
    }

    public function getImageKey($img, $pool = 'admin')
    {
        $tenant_access_token = $this->tenantAccessToken($pool);
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

    public function sendUserToken($pool = 'admin')
    {
        return $this->tenantAccessToken($pool);
    }

    public function setInner($inner)
    {
        $this->inner = $inner;
    }

    /**
     * 自建应用获取 tenant_access_token
     * @document https://open.feishu.cn/document/server-docs/authentication-management/access-token/tenant_access_token_internal
     * @return mixed|string
     * @throws \EasySwoole\HttpClient\Exception\InvalidUrl
     */
    public function tenantAccessToken($pool = 'admin')
    {
        $appId = config('ES_NOTIFY.feishu.appId');
        $appSecret = config('ES_NOTIFY.feishu.appSecret');
        $key = "tenant_access_token-{$appId}";
        $Redis = defer_redis($pool);
        $token = $Redis->get($key);
        if ( ! empty($token)) {
            return $token;
        }

        $sendParams = [
            'app_id' => $appId,
            'app_secret' => $appSecret,
        ];

        $result = hcurl('https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal', $sendParams);
        if (isset($result['code']) && $result['code'] == 0) {
            $Redis->setEx($key, $result['expire'] - 60, $result['tenant_access_token']);
            return $result['tenant_access_token'];
        }
        return '';
    }
}
