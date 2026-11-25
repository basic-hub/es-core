<?php

namespace BasicHub\EsCore\Task;

use BasicHub\EsCore\Common\Classes\CtxManager;
use BasicHub\EsCore\Common\Classes\DateUtils;
use BasicHub\EsCore\Constants;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use EasySwoole\Utility\File;

/**
 * 程序异常
 */
class Error implements TaskInterface
{
    protected $warp = " \n\n ";

    protected $data = [];

    protected $merge = [];

    public function __construct($data = [], $merge = [])
    {
        $this->data = $data;
        $this->merge = $merge;
    }

    public function run(int $taskId, int $workerIndex)
    {
        if (!$this->checkTime()) {
            return;
        }
        $title = '程序异常';
        $servname = config('SERVNAME');
        $servername = config('SERVER_NAME');

        if (!empty($this->data['htid'])) {
            ctx_set(CtxManager::HTTP_TRACKER_PARENTID, $this->data['htid']);
        }

        $driver = config('ES_NOTIFY.driver');
        is_string($driver) && $driver = [$driver];

        // 获取错误消息内容
        $message = $this->data['message'] ?? '';
        foreach ($this->merge as $key => $value) {
            $message .= "\n" . (is_int($key) ? $value : "$key: $value");
        }

        // 飞书
        if (is_array($driver) && in_array(Constants::NOTIFY_FEISHU, $driver)) {
            // 东8区用默认模板，非东8区使用带时区的模板
            if (DateUtils::isInTimeZone(8)) {
                $Message = new \BasicHub\EsCore\Common\CloudLib\Notify\Message\Feishu\CardError([
                    'servername' => $servname,
                    'project' => $servername,
                    'datetime' => date(DateUtils::FULL),
                    'trigger' => $this->data['trigger'] ?? '',
                    'filename' => "{$this->data['file']} 第 {$this->data['line']} 行",
                    'content' => $message,
                    'isAtAll' => true
                ]);
            } else {
                $Message = new \BasicHub\EsCore\Common\CloudLib\Notify\Message\Feishu\CardErrorTimeZone([
                    // 东8区时间
                    'datetime8' => DateUtils::timestampToDatetime(time()),
                    // 服务器本地时间
                    'datetimeLocal' => date(DateUtils::FULL),
                    // 服务器时区：UTC-5:00、UTC+8:00
                    'timeZone' => DateUtils::getTimeZoneUTC(),
                    'servername' => $servname,
                    'project' => $servername,
                    'trigger' => $this->data['trigger'] ?? '',
                    'filename' => "{$this->data['file']} 第 {$this->data['line']} 行",
                    'content' => $message,
                    'isAtAll' => true
                ]);
            }
            notify_message($Message, Constants::NOTIFY_FEISHU);
        }

        // 钉钉
        if (is_array($driver) && in_array(Constants::NOTIFY_DINGTALK, $driver)) {
            // 钉钉 markdown
            $data = [
                '### **' . $title . '**',
                '- 服务器: ' . $servname,
                '- 项 目：' . $servername,
                "- 文 件：{$this->data['file']} 第 {$this->data['line']} 行",
                "- 详 情：" . ($this->data['message'] ?? ''),
                '- 触发方式： ' . ($this->data['trigger'] ?? ''),
            ];

            foreach ($this->merge as $key => $value) {
                $data[] = "- " . (is_int($key) ? $value : "$key: $value");
            }

            $text = implode($this->warp, $data);
            $Message = new \BasicHub\EsCore\Common\CloudLib\Notify\Message\Dingtalk\Markdown([
                'title' => $title,
                'text' => $text,
                'isAtAll' => true
            ]);
            notify_message($Message, Constants::NOTIFY_DINGTALK);
        }

        // 企业微信
        if (is_array($driver) && in_array(Constants::NOTIFY_WECOM, $driver)) {
            // 企业微信使用 Markdown 格式
            $data = [
                '### **' . $title . '**',
                '- 服务器: ' . $servname,
                '- 项 目：' . $servername,
                "- 文 件：{$this->data['file']} 第 {$this->data['line']} 行",
                "- 详 情：" . ($this->data['message'] ?? ''),
                '- 触发方式： ' . ($this->data['trigger'] ?? ''),
            ];

            foreach ($this->merge as $key => $value) {
                $data[] = "- " . (is_int($key) ? $value : "$key: $value");
            }

            $text = implode($this->warp, $data);
            $Message = new \BasicHub\EsCore\Common\CloudLib\Notify\Message\WeCom\Markdown([
                'content' => $text,
                'isAtAll' => true
            ]);
            notify_message($Message, Constants::NOTIFY_WECOM);
        }

        // 微信
        if (is_array($driver) && in_array(Constants::NOTIFY_WECHAT, $driver)) {
            $Message = new \BasicHub\EsCore\Common\CloudLib\Notify\Message\Wechat\Notice([
                'content' => "$title: {$this->data['message']}, 文件 {$this->data['file']} 第 {$this->data['line']} 行",
            ]);
            notify_message($Message, Constants::NOTIFY_WECHAT);
        }
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        trace($throwable->__toString(), 'error');
    }

    /**
     * 同一个文件出错，N分钟内不重复发送
     * @return bool
     */
    protected function checkTime()
    {
        $file = $this->data['file'];
        if ( ! $file) {
            return false;
        }
        $time = time();
        $strId = md5($file);
        $chkFile = config('LOG.dir') . '/checktime.data';
        File::touchFile($chkFile, false);
        $content = file_get_contents($chkFile);
        if ($arr = json_decode($content, true)) {
            $last = $arr[$strId] ?? '';
            $limit = (config('err_limit_time') ?: 5) * 60;
            if ($last && $limit && $last > $time - $limit) {
                // 时间未到
                return false;
            }
        }
        $arr[$strId] = $time;
        file_put_contents($chkFile, json_encode($arr));
        return true;
    }
}
