<?php

namespace BasicHub\EsCore\Common\Logs;

use EasySwoole\Spl\SplBean;
use Swoole\Coroutine;
use BasicHub\EsCore\Common\Classes\DateUtils;

class Item extends SplBean
{
    /**
     * 无需清除换行符的category，例如支持原样输出\EasySwoole\Utility\ArrayToTextTable等结构
     */
    const CATEGORY_REAL = 'real';

    public $level = '';

    public $category = '';

    public $message = '';

    /************* 以下为自动完成的私有属性，勿传 ************/

    private $cid = -1;
    private $time = 0;
    private $date = '';

    protected function initialize(): void
    {
        parent::initialize();
        $this->cid = Coroutine::getCid();

        if ( ! is_scalar($this->message)) {
            $this->message = json_encode($this->message, JSON_UNESCAPED_UNICODE);
        }

        if ($this->category !== self::CATEGORY_REAL) {
            $this->message = str_replace(["\n", "\r"], '', $this->message);
        }

        // 产生日志的时间
        $this->time = time();
        $this->date = date(DateUtils::FULL, $this->time);
        // 不在东8区，则记录东8区时间
        if ( ! DateUtils::isInTimeZone(8)) {
            $this->date .= ', +8区: ' . DateUtils::timestampToDatetime($this->time);
        }
    }

    public function getWriteStr()
    {
        return "[cid={$this->cid}][{$this->date}][{$this->category}][{$this->level}]{$this->message}";
    }

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
        throw new \Exception(__CLASS__ . ' Not fount property : ' . $name);
    }
}
