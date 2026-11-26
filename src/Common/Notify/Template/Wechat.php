<?php

namespace BasicHub\EsCore\Common\Notify\Template;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;
use EasySwoole\WeChat\Factory;
use EasySwoole\WeChat\OfficialAccount\Application as OfficialAccount;

/**
 * 微信公众号通知驱动
 * @document https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Template_Message_Interface.html
 */
class Wechat extends Base
{
    /**
     * 微信公众平台后台的 appid
     * @var string
     */
    protected $appId = '';

    /**
     * 微信公众平台后台配置的 AppSecret
     * @var string
     */
    protected $appSecret = '';

    /**
     * 微信公众平台后台配置的 Token
     * @var string
     */
    protected $token = '';

    /**
     * 消息模板id
     * @var string
     */
    protected $templateId = '';

    /**
     * 发送给谁, openid[]
     * @var array
     */
    protected $toOpenid = [];

    /**
     * 点击后跳转地址
     * @var string
     */
    protected $url = '';

    /**
     * @var OfficialAccount
     */
    private $OfficialAccount = null;

    /**
     * 获取微信公众号实例
     * @return OfficialAccount
     */
    protected function getOfficialAccount()
    {
        if ($this->OfficialAccount === null) {
            $this->OfficialAccount = Factory::officialAccount([
                'appId' => $this->appId,
                'token' => $this->token,
                'appSecret' => $this->appSecret
            ]);
        }
        return $this->OfficialAccount;
    }

    /**
     * 实际发送模板消息
     * @document http://www.easyswoole.com/Components/WeChat2.x/officialAccount/templateMessage.html
     * @param MessageInterface $message
     * @return array
     */
    public function send(MessageInterface $message)
    {
        if ($this->isdebug) {
            trace(__METHOD__ . ', message=' . var_export($message->fullData(), true));
            return true;
        }

        $url = $this->url;
        $toOpenid = $this->toOpenid;

        $data = $message->fullData();

        $result = [];
        foreach ($toOpenid as $openid) {
            try {
                $resp = $this->getOfficialAccount()->templateMessage->send([
                    'touser' => $openid,
                    'template_id' => $this->templateId,
                    'url' => $url,
                    'data' => $data,
                ]);
            } catch (\Throwable | \Exception $e) {
                // 未关注、取消关注 或 其他错误
                if ($this->isdebug) {
                    trace("微信模板消息发送失败: openid={$openid}, error=" . $e->getMessage(), 'error');
                }
                $resp = false;
            }
            $result[$openid] = $resp;
        }

        return $result;
    }
}
