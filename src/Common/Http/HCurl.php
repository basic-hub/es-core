<?php

namespace BasicHub\EsCore\Common\Http;

use EasySwoole\HttpClient\HttpClient;

/**
 * EasySwoole 协程 HTTP 客户端（非阻塞，适用于 Swoole 协程环境）
 *
 * 用法示例:
 *   // 基础 GET
 *   $result = (new HCurl(['url' => 'https://api.example.com/list']))->request(['page' => 1]);
 *
 *   // POST JSON
 *   $client = new HCurl([
 *       'url'    => 'https://api.example.com/create',
 *       'method' => 'json',
 *   ]);
 *   $result = $client->request(['name' => 'test']);
 *
 *   // 文件上传（multipart，通过 addData）
 *   $client = new HCurl([
 *       'url'     => 'https://upload.example.com/file',
 *       'method'  => 'post',
 *       'addData' => ['field' => 'value', 'file' => new \CURLFile('/tmp/a.jpg')],
 *   ]);
 *   $client->request();
 *
 *   // 下载文件（断点续传）
 *   $client = new HCurl([
 *       'url'            => 'https://example.com/big.zip',
 *       'method'         => 'download',
 *       'downloadOffset' => 1024 * 1024, // 从 1MB 处继续
 *       'resultType'     => 'body',
 *   ]);
 *   $client->request();
 *
 *   // 自定义处理 HttpClient 实例（设置任意底层参数）
 *   $client = new HCurl([
 *       'url'        => 'https://api.example.com',
 *       'dealClient' => function (HttpClient $client): HttpClient {
 *           $client->addCookies(['session' => 'abc123']);
 *           return $client;
 *       },
 *   ]);
 */
class HCurl extends Base
{
    /**
     * Swoole\Coroutine\Http\Client 底层设置
     * 参考: https://wiki.swoole.com/zh-cn/#/coroutine_client/http_client?id=set
     * 常用配置:
     *   keepAlive         bool   是否保持连接（默认 true）
     *   timeout           float  读取超时（秒）
     *   connect_timeout   float  连接超时（秒）
     *   ssl_verify_peer   bool   是否验证证书
     *   ssl_cafile        string CA 证书路径
     * @var array
     */
    protected $clientSet = ['keepAlive' => true];

    /**
     * 连接超时时间（秒），0 不限制
     * 会合并到 clientSet，优先级高于 clientSet 中的 connect_timeout
     * @var float
     */
    protected $connectTimeout = 10.0;

    /**
     * 读取超时时间（秒），0 不限制
     * 会合并到 clientSet，优先级高于 clientSet 中的 timeout
     * @var float
     */
    protected $readTimeout = 30.0;

    /**
     * 通过 Swoole\Coroutine\Http\Client::addData() 逐条添加的 multipart 数据
     * CURLFile 类型会被跳过（协程客户端不支持）
     * ['field1' => 'value1', 'field2' => 'value2']
     * @var array
     */
    protected $addData = [];

    /**
     * 通过 Swoole\Coroutine\Http\Client::setData() 整体设置的请求体
     * 会覆盖 addData，适用于需要原始字符串 body 的场景
     * @var mixed
     */
    protected $setData = null;

    /**
     * 下载时的起始偏移量（字节），用于断点续传
     * 仅 method = 'download' 时有效
     * @var int
     */
    protected $downloadOffset = 0;

    /**
     * 自定义处理 HttpClient 实例的回调，可用于设置任意底层参数
     * 签名: function(HttpClient $client): HttpClient
     * 在所有属性配置完成后、实际发送请求前执行
     * @var callable|null
     */
    protected $dealClient = null;

    /**
     * HTTP/SOCKS5 代理配置
     * [
     *   'host'     => '127.0.0.1',
     *   'port'     => 1080,
     *   'username' => '',   // 可选
     *   'password' => '',   // 可选
     * ]
     * @var array|null
     */
    protected $proxy = null;

    /**
     * 代理类型: 'http' | 'socks5'
     * @var string
     */
    protected $proxyType = 'http';

    /**
     * Cookie 键值对，会通过 addCookies() 设置
     * ['session_id' => 'xxx', 'token' => 'yyy']
     * @var array
     */
    protected $cookies = [];

    /**
     * @inheritDoc
     */
    protected function send($data): array
    {
        $client = new HttpClient($this->url);

        // --- 客户端基础设置 ---
        $settings = $this->clientSet;
        $settings['keepAlive'] = $settings['keepAlive'] ?? true;
        if ($this->connectTimeout > 0) {
            $settings['connect_timeout'] = $this->connectTimeout;
        }
        if ($this->readTimeout > 0) {
            $settings['timeout'] = $this->readTimeout;
        }
        $client->setClientSettings($settings, false);

        // --- 请求头 ---
        if ($this->headers) {
            $client->setHeaders($this->headers, false, false);
        }

        // --- SSL ---
        if (stripos($this->url, 'https://') !== false) {
            $client->setEnableSSL();
        }

        // --- 代理 ---
        if ($this->proxy) {
            $host = $this->proxy['host']     ?? '';
            $port = $this->proxy['port']     ?? 0;
            $user = $this->proxy['username'] ?? '';
            $pass = $this->proxy['password'] ?? '';
            if ($this->proxyType === 'socks5') {
                $client->setProxySocks5($host, $port, $user, $pass);
            } else {
                $client->setProxyHttp($host, $port, $user, $pass);
            }
        }

        // --- Cookie ---
        if ($this->cookies) {
            $client->addCookies($this->cookies);
        }

        // --- 自定义处理（在 addData/setData 之前，允许回调里也做这些操作）---
        if (is_callable($this->dealClient)) {
            $client = ($this->dealClient)($client);
        }

        // --- multipart addData（跳过 CURLFile，协程客户端不支持）---
        if (!empty($this->addData)) {
            $cli = $client->getClient();
            foreach ($this->addData as $key => $value) {
                if ($value instanceof \CURLFile) {
                    continue;
                }
                if (is_array($value)) {
                    $hasFile = false;
                    foreach ($value as $v) {
                        if ($v instanceof \CURLFile) {
                            $hasFile = true;
                            break;
                        }
                    }
                    if ($hasFile) {
                        continue;
                    }
                }
                $cli->addData($key, $value);
            }
        }

        // --- setData（整体覆盖 body）---
        if (!empty($this->setData)) {
            $client->getClient()->setData($this->setData);
        }

        $method = $this->method;
        switch ($method) {
            case 'get':
                $data && $client->setQuery($data);
                $response = $client->get();
                break;
            case 'post':
                $response = $client->post($data);
                break;
            case 'put':
                $response = $client->put($data);
                break;
            case 'delete':
                $data && $client->setQuery($data);
                $response = $client->delete();
                break;
            case 'patch':
                $response = $client->patch($data);
                break;
            case 'head':
                $response = $client->head();
                break;
            case 'options':
                $response = $client->options();
                break;
            case 'xml':
                $response = $client->postXml($data);
                break;
            case 'json':
                $payload = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
                $response = $client->postJson($payload);
                break;
            case 'download':
                $response = $client->download($this->url, $this->downloadOffset, HttpClient::METHOD_GET, $data);
                break;
            default:
                $response = $client->$method($data);
                break;
        }

        /** @var \EasySwoole\HttpClient\Bean\Response $response */
        return [$response->getStatusCode(), $response->getBody(), $response];
    }
}
