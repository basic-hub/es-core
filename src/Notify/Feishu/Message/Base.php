<?php

namespace BasicHub\EsCore\Notify\Feishu\Message;

use BasicHub\EsCore\Common\Classes\DateUtils;
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
                '服务器时间：' . date(DateUtils::FULL) . ' （' . DateUtils::getTimeZoneUTC() . '）'
            ]);
    }

    public function setInner($inner)
    {
        $this->inner = $inner;
    }
}
