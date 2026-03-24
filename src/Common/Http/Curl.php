<?php

namespace BasicHub\EsCore\Common\Http;

/**
 * 标准 PHP cURL 客户端（阻塞式，适用于非协程环境）
 *
 * 用法示例:
 *   // 基础 GET
 *   $result = (new Curl(['url' => 'https://api.example.com/list']))->request(['page' => 1]);
 *
 *   // POST JSON + 超时 + 代理
 *   $client = new Curl([
 *       'url'            => 'https://api.example.com/create',
 *       'method'         => 'json',
 *       'timeout'        => 10,
 *       'proxy'          => '127.0.0.1',
 *       'proxyPort'      => 1080,
 *       'proxyType'      => CURLPROXY_SOCKS5,
 *   ]);
 *   $result = $client->request(['name' => 'test']);
 *
 *   // 上传文件（multipart POST）
 *   $client = new Curl([
 *       'url'    => 'https://upload.example.com/file',
 *       'method' => 'post',
 *   ]);
 *   $result = $client->request(['file' => new \CURLFile('/tmp/a.jpg')]);
 *
 *   // 下载文件（保存到本地）
 *   $client = new Curl([
 *       'url'         => 'https://example.com/file.zip',
 *       'method'      => 'get',
 *       'resultType'  => 'body',
 *       'curlOptions' => [CURLOPT_FILE => fopen('/tmp/file.zip', 'w')],
 *   ]);
 *   $client->request();
 */
class Curl extends Base
{
    /**
     * 连接超时时间（秒），0 不限制
     * @var int
     */
    protected $connectTimeout = 30;

    /**
     * 请求总超时时间（秒），0 不限制
     * @var int
     */
    protected $timeout = 30;

    /**
     * 是否跟随 3xx 重定向
     * @var bool
     */
    protected $followRedirect = true;

    /**
     * 最大重定向次数
     * @var int
     */
    protected $maxRedirects = 5;

    /**
     * User-Agent 字符串，空则使用 curl 默认值
     * @var string
     */
    protected $userAgent = '';

    /**
     * 代理服务器地址（IP 或域名）
     * @var string
     */
    protected $proxy = '';

    /**
     * 代理端口
     * @var int
     */
    protected $proxyPort = 0;

    /**
     * 代理协议类型，使用 CURLPROXY_* 常量
     * 常用值: CURLPROXY_HTTP | CURLPROXY_SOCKS5
     * @var int
     */
    protected $proxyType = CURLPROXY_HTTP;

    /**
     * 代理认证凭据，格式 'user:password'
     * @var string
     */
    protected $proxyAuth = '';

    /**
     * Cookie 字符串，格式 'key=value; key2=value2'
     * @var string
     */
    protected $cookie = '';

    /**
     * Cookie 持久化文件路径（同时作为读取和写入路径）
     * @var string
     */
    protected $cookieJar = '';

    /**
     * SSL 客户端证书文件路径
     * @var string
     */
    protected $sslCert = '';

    /**
     * SSL 证书类型: PEM | DER | ENG
     * @var string
     */
    protected $sslCertType = 'PEM';

    /**
     * SSL 私钥文件路径
     * @var string
     */
    protected $sslKey = '';

    /**
     * SSL 私钥密码
     * @var string
     */
    protected $sslKeyPassword = '';

    /**
     * 是否验证 SSL 证书合法性
     * 生产环境建议开启；自签名证书场景设为 false
     * @var bool
     */
    protected $sslVerify = false;

    /**
     * 是否在响应体中包含响应头
     * @var bool
     */
    protected $withResponseHeaders = false;

    /**
     * 原始 CURLOPT_* 选项，优先级最高，可覆盖以上所有配置
     * 例: [CURLOPT_INTERFACE => 'eth0', CURLOPT_FILE => $fp]
     * @var array
     */
    protected $curlOptions = [];

    /**
     * @inheritDoc
     */
    protected function send($data): array
    {
        $url     = $this->url;
        $headers = $this->headers; // 使用局部副本，避免污染实例属性

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify ? 2 : 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if ($this->followRedirect) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $this->maxRedirects);
        }

        if ($this->userAgent) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        }

        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
            curl_setopt($ch, CURLOPT_PROXYTYPE, $this->proxyType);
            $this->proxyPort && curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxyPort);
            $this->proxyAuth && curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxyAuth);
        }

        if ($this->cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
        }

        if ($this->cookieJar) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        }

        if ($this->sslCert) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->sslCert);
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, $this->sslCertType);
        }

        if ($this->sslKey) {
            curl_setopt($ch, CURLOPT_SSLKEY, $this->sslKey);
            $this->sslKeyPassword && curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $this->sslKeyPassword);
        }

        if ($this->withResponseHeaders) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }

        // --- 请求方式处理 ---
        switch ($this->method) {
            case 'get':
                if ($data) {
                    $sep  = strpos($url, '?') === false ? '?' : '&';
                    $url .= $sep . (is_array($data) ? http_build_query($data) : $data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;

            case 'post':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;

            case 'put':
            case 'delete':
            case 'patch':
            case 'options':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($this->method));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;

            case 'head':
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
                break;

            case 'xml':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                $headers['Content-Type'] = 'text/xml; charset=utf-8';
                break;

            case 'json':
                curl_setopt($ch, CURLOPT_POST, true);
                $payload = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $headers['Content-Type'] = 'application/json; charset=utf-8';
                break;

            default:
                curl_close($ch);
                throw new \Exception("不支持的请求方式：{$this->method}");
        }

        // --- 请求头 ---
        if ($headers) {
            $headerArray = array_map(function($k, $v) { return "$k: $v"; }, array_keys($headers), array_values($headers));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        }

        // 原始 curlOptions 优先级最高，会覆盖以上所有设置
        foreach ($this->curlOptions as $key => $val) {
            curl_setopt($ch, $key, $val);
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new \Exception($error, $errno);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$httpCode, (string)$response];
    }
}
