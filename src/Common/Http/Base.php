<?php

namespace BasicHub\EsCore\Common\Http;

use EasySwoole\Spl\SplBean;
use Swoole\Coroutine;

/**
 * HTTP 请求基类
 *
 * 通过 SplBean 在实例化时设置属性，再调用 request() 发送请求。
 * 子类只需实现 send() 方法即可，各自的专属配置以属性形式定义。
 * request() 始终返回 HttpResponse，由调用方自主决定如何解析响应体。
 *
 * 基本用法:
 *   $client = new Curl(['url' => 'https://example.com', 'method' => 'get']);
 *   $Resp = $client->request(['page' => 1]);
 *   $data = $Resp->json();
 *
 * 重试用法:
 *   $client = new HCurl([
 *       'url'           => 'https://api.example.com/data',
 *       'retryTimes'    => 3,
 *       'retryCallback' => function (HttpResponse $Resp) {
 *           return $Resp->code === 200;
 *       },
 *   ]);
 */
abstract class Base extends SplBean implements HttpInterface
{
    /**
     * 请求的 URL
     * @var string
     */
    protected $url = '';

    /**
     * 请求方式: get|post|put|delete|patch|head|options|xml|json|download
     * @var string
     */
    protected $method = 'post';

    /**
     * 请求头，关联数组形式 ['Content-Type' => 'application/json']
     * @var array
     */
    protected $headers = [];

    /**
     * http_tracker名，传 null 时不记录链路，例如在自定义进程或crontab程序中运行时
     * @var string|null
     */
    protected $htname = 'http-request';

    /**
     * 请求失败（网络异常）时是否抛出异常，false 时返回 code=0 的 HttpResponse
     * @var bool
     */
    protected $throw = true;

    /**
     * 请求失败后的最大重试次数，0 表示不重试
     * @var int
     */
    protected $retryTimes = 0;

    /**
     * 判断请求是否成功的回调，返回 true 表示成功无需重试，返回 false 触发重试
     * function(HttpResponse $Resp): bool
     * null 时使用默认策略（2xx/3xx 为成功）
     * false 时禁用重试判断（即使 retryTimes > 0 也不重试）
     * @var \Closure|false|null
     */
    protected $retryCallback = null;

    /**
     * 重试间隔时间（秒），支持小数
     * @var float
     */
    protected $retryInterval = 0.5;

    /**
     * 内部重试计数，每次顶层 request() 调用自动重置
     * @var int
     */
    private $retryCurt = 0;

    /**
     * 日志关键字，用于在海量日志中快速定位
     * @var string
     */
    protected $logKeyword = '';

    /**
     * 失败日志级别
     * @var string
     */
    protected $logErrorLevel = 'error';

    /**
     * 失败日志分类
     * @var string
     */
    protected $logErrorCategory = 'debug';

    protected function initialize(): void
    {
        if (is_null($this->retryCallback)) {
            $this->retryCallback = function (HttpResponse $Resp): bool {
                return in_array($Resp->code, [200, 201, 202, 204, 302, 303, 307]);
            };
        }

        $this->method = strtolower($this->method);
    }

    /**
     * 执行实际 HTTP 请求，由子类实现
     * @param  mixed $data  请求数据
     * @return array        [int $httpCode, string $body, mixed $rawObject = null]
     * @throws \Exception
     */
    abstract protected function send($data): array;

    /**
     * 发起请求（对外统一入口）
     *
     * @param  array|string $data  请求数据，GET 时会拼到 URL 上，xml 时传字符串
     * @return HttpResponse
     * @throws \Exception|\Error
     */
    public function request($data = [])
    {
        if (empty($this->url)) {
            $e = '发起请求失败！非法url: ' . $this->url;
            trace($e, 'error');
            throw new \Error($e);
        }

        return $this->doRequest($data);
    }

    /**
     * 内部请求执行（支持递归重试）
     */
    private function doRequest($data)
    {
        $End = null;
        try {
            if (!empty($this->htname)) {
                $End = http_tracker($this->htname, [
                    'method'  => strtoupper($this->method),
                    'url'     => $this->url,
                    'data'    => $data,
                    'headers' => $this->headers,
                ]);
            }

            $sendResult = $this->send($data);
            $httpCode   = $sendResult[0];
            $raw        = $sendResult[1];
            $rawObject  = $sendResult[2] ?? null;

            is_callable($End) && $End($raw, $httpCode);
        } catch (\Exception $e) {
            $err = "{$this->logKeyword} 请求失败！信息为：{$e->getMessage()} 传参为："
                . json_encode(['url' => $this->url, 'data' => $data], JSON_UNESCAPED_UNICODE);
            trace($err, $this->logErrorLevel, $this->logErrorCategory);
            is_callable($End) && $End($err, $e->getCode());
            if ($this->throw) {
                throw $e;
            }
            return new HttpResponse(0, $e->getMessage());
        }

        $HttpResponse = new HttpResponse($httpCode, $raw, $rawObject);

        // 重试
        $call = $this->retryCallback;
        if ($this->retryTimes > 0 && is_callable($call) && !$call($HttpResponse)) {
            if ($this->retryCurt < $this->retryTimes) {
                $this->sleep($this->retryInterval);
                $this->retryCurt++;
                return $this->doRequest($data);
            }

            $err = "{$this->logKeyword} 响应失败！HTTP状态码为：{$httpCode}，响应内容为：{$raw}，传参为："
                . json_encode(['url' => $this->url, 'data' => $data], JSON_UNESCAPED_UNICODE);
            trace($err, $this->logErrorLevel, $this->logErrorCategory);

            if ($this->throw) {
                throw new \Exception($raw, $httpCode);
            }
        }
        return $HttpResponse;
    }

    /**
     * 兼容协程与非协程环境的 sleep
     */
    private function sleep(float $seconds): void
    {
        if (Coroutine::getCid() > 0) {
            Coroutine::sleep($seconds);
        } else {
            usleep((int)($seconds * 1_000_000));
        }
    }
}
