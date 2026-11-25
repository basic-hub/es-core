<?php

namespace BasicHub\EsCore\Common\CloudLib\Notify\Message\WeCom;

use BasicHub\EsCore\Common\Classes\DateUtils;
use EasySwoole\Spl\SplBean;
use BasicHub\EsCore\Common\CloudLib\Notify\MessageInterface;

abstract class Base extends SplBean implements MessageInterface
{
    /**
     * 被@人的成员userid列表
     * @var array
     */
    protected $mentioned_list = [];

    /**
     * 被@人的手机号列表
     * @var array
     */
    protected $mentioned_mobile_list = [];

    /**
     * 是否@所有人
     * @var bool
     */
    protected $isAtAll = false;

    /**
     * 获取@相关参数
     * @return array
     */
    public function getAtParams()
    {
        $params = [];
        
        if ($this->isAtAll) {
            $params['mentioned_list'] = ['@all'];
        } elseif (!empty($this->mentioned_list)) {
            $params['mentioned_list'] = $this->mentioned_list;
        }
        
        if (!empty($this->mentioned_mobile_list)) {
            $params['mentioned_mobile_list'] = $this->mentioned_mobile_list;
        }
        
        return $params;
    }

    /**
     * 获取服务器信息文本
     * @return string
     */
    public function getServerText()
    {
        return PHP_EOL . implode(PHP_EOL, [
                '系统：' . APP_MODULE,
                '服务器：' . config('SERVNAME'),
                '服务器时间：' . date(DateUtils::FULL) . ' （' . DateUtils::getTimeZoneUTC() . '）'
            ]);
    }
}
