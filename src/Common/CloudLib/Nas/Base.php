<?php

namespace BasicHub\EsCore\Common\CloudLib\Nas;

use EasySwoole\HttpClient\HttpClient;
use EasySwoole\Spl\SplBean;

/**
 * NAS 驱动基类
 *
 * NAS 厂商基本都没有 PHP SDK，统一使用原生 HTTP 实现，不引入额外依赖。
 *
 * 公共配置：
 *   host     NAS 地址，如 https://192.168.1.2:5001
 *   username 登录账号
 *   password 登录密码
 */
abstract class Base extends SplBean implements NasInterface
{
    /**
     * NAS 主机地址，必须包含协议和端口
     * 示例：https://192.168.1.100:5001 或 http://nas.local:8080
     * @var string
     */
    protected $host = '';

    /**
     * 登录账号
     * @var string
     */
    protected $username = '';

    /**
     * 登录密码
     * @var string
     */
    protected $password = '';

    /**
     * 两步验证动态码，未开启 2FA 时留空
     * @var string
     */
    protected $otpCode = '';

    /**
     * 是否校验 SSL 证书
     * NAS 多为内网自签证书，默认关闭
     * @var bool
     */
    protected $sslVerify = false;

    /**
     * 连接超时（秒）
     * @var float
     */
    protected $connectTimeout = 10.0;

    /**
     * 读写超时（秒）
     * 上传/下载大文件需要调大
     * @var float
     */
    protected $readTimeout = 300.0;

    /**
     * 异步任务（如群晖的 Delete/CopyMove）轮询间隔（秒）
     * @var float
     */
    protected $taskInterval = 0.5;

    /**
     * 异步任务最大轮询次数，超出视为超时
     * @var int
     */
    protected $taskMaxTimes = 600;

    /**
     * 当前会话凭证，登录后写入
     * 各厂商叫法不同：威联通为 sid，群晖为 _sid，TrueNAS 为 API Key
     * @var string
     */
    protected $sessionToken = '';

    /**
     * read() 默认允许读取的最大字节数
     *
     * 内容要完整进内存，默认限制 10MB，避免误读大文件把进程打爆。
     * @var int
     */
    protected $maxReadSize = 10485760;

    /**
     * 当前驱动支持的能力，子类按实际 API 能力覆盖
     * @var array
     */
    protected $abilities = [
        'listDir'    => true,
        'exists'     => true,
        'mkdir'      => true,
        'upload'     => true,
        'download'   => true,
        'delete'     => true,
        'copy'       => true,
        'move'       => true,
        'rename'     => true,
        'read'       => false,
        'thumbnail'  => false,
        'previewUrl' => false,
    ];

    protected function initialize(): void
    {
        $this->host = rtrim($this->host, '/');
        
        if (empty($this->host)) {
            throw new \Exception('NAS host 不能为空');
        }
        
        // 必须包含协议（http:// 或 https://）
        if (!preg_match('#^https?://#i', $this->host)) {
            throw new \Exception('NAS host 必须包含协议前缀（http:// 或 https://），当前值：' . $this->host);
        }
    }

    /**
     * 当前驱动名，与 Storage 保持一致的用法
     * @return string
     */
    protected function getClassName()
    {
        $arr = explode('\\', static::class);
        return end($arr);
    }

    public function can($ability)
    {
        return !empty($this->abilities[$ability]);
    }

    /**
     * 获取会话凭证，未登录时自动登录
     * @return string
     */
    protected function getSessionToken()
    {
        if (empty($this->sessionToken)) {
            $this->login();
        }
        return $this->sessionToken;
    }

    /**
     * 规范化 NAS 路径：统一为正斜线、以 / 开头、去掉末尾 /
     * @param string $path
     * @return string
     */
    protected function normalizePath($path)
    {
        $path = str_replace('\\', '/', (string)$path);
        $path = preg_replace('#/+#', '/', $path);
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    /**
     * 拆分路径为 [父目录, 名称]
     * @param string $path
     * @return array
     */
    protected function splitPath($path)
    {
        $path = $this->normalizePath($path);
        $pos = strrpos($path, '/');
        $dir = $pos > 0 ? substr($path, 0, $pos) : '/';
        $name = substr($path, $pos + 1);
        return [$dir, $name];
    }

    /**
     * 构造完整请求 URL
     * @param string $uri 接口路径，如 /webapi/entry.cgi
     * @param array $query 查询参数
     * @return string
     */
    protected function buildUrl($uri, array $query = [])
    {
        // host 已在 initialize() 中去除尾部斜线，这里直接拼接
        $url = $this->host . '/' . ltrim($uri, '/');
        
        if (empty($query)) {
            return $url;
        }
        
        $queryString = $this->buildQuery($query);
        if (empty($queryString)) {
            return $url;
        }
        
        return $url . (strpos($url, '?') === false ? '?' : '&') . $queryString;
    }

    /**
     * 构造查询串
     *
     * 与 http_build_query 的区别：数组值会展开为重复的同名参数（source_file=a&source_file=b），
     * 而不是 source_file[0]=a。威联通的多文件操作必须用这种形式。
     * @param array $query
     * @return string
     */
    protected function buildQuery(array $query)
    {
        $parts = [];
        foreach ($query as $key => $value) {
            if (is_null($value)) {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $parts[] = urlencode($key) . '=' . urlencode($item);
                }
                continue;
            }
            $parts[] = urlencode($key) . '=' . urlencode($value);
        }
        return implode('&', $parts);
    }

    /**
     * 创建并预配置 HttpClient
     * @param string $uri 接口路径（不含 host）
     * @param array $query 查询参数
     * @return HttpClient
     * @throws \EasySwoole\HttpClient\Exception\InvalidUrl
     */
    protected function newClient($uri, array $query = [])
    {
        $parts = parse_url($this->host);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            throw new \Exception('无效的 host 配置: ' . $this->host);
        }

        $baseUrl = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $baseUrl .= ':' . $parts['port'];
        }

        $client = new HttpClient($baseUrl);
        
        // 设置路径
        $client->setPath($uri);
        
        // 设置查询参数（保留数组结构，让 HttpClient 自己处理）
        if ($query) {
            $client->setQuery($query);
        }

        // SSL 配置
        if ($parts['scheme'] === 'https') {
            $client->setEnableSSL(true);
            $client->setSslVerifyPeer($this->sslVerify, !$this->sslVerify);
        }
        
        $client->setClientSetting('connect_timeout', $this->connectTimeout);
        $client->setClientSetting('timeout', $this->readTimeout);
        return $client;
    }

    /**
     * 发起请求并返回原始响应体
     * @param string $uri 接口路径
     * @param array $query 查询参数
     * @param string $method get|post
     * @param mixed $body POST 请求体
     * @param array $headers 额外请求头
     * @return string
     * @throws \Exception
     */
    protected function request($uri, array $query = [], $method = 'get', $body = null, array $headers = [])
    {
        $url = $this->buildUrl($uri, $query);

        // 调试输出，确认实际请求 URL
        trace(sprintf('[%s] %s %s', $this->getClassName(), strtoupper($method), $url), 'debug', 'nas');

        $End = null;
        if (function_exists('http_tracker')) {
            $End = http_tracker($this->getClassName() . ':nas', [
                'method' => strtoupper($method),
                'url'    => $url,
            ]);
        }

        try {
            $client = $this->newClient($uri, $query);
            if ($headers) {
                $client->setHeaders($headers, true, false);
            }

            if (strtolower($method) === 'post') {
                $response = $client->post($body);
            } else {
                $response = $client->get();
            }

            $errCode = $response->getErrCode();
            if ($errCode !== 0) {
                throw new \Exception(sprintf(
                    '%s 请求失败 [Swoole errCode=%d errMsg=%s] url=%s',
                    $this->getClassName(), $errCode, $response->getErrMsg(), $url
                ));
            }

            $raw = (string)$response->getBody();
            is_callable($End) && $End($raw, $response->getStatusCode());
            return $raw;
        } catch (\Exception $e) {
            trace($this->getClassName() . ' NAS请求异常: ' . $e->getMessage() . ' url=' . $url, 'error');
            is_callable($End) && $End($e->getMessage(), $e->getCode());
            throw $e;
        }
    }

    /**
     * 发起请求并解析 JSON 响应
     * @param string $uri
     * @param array $query
     * @param string $method
     * @param mixed $body
     * @param array $headers
     * @return array
     * @throws \Exception
     */
    protected function requestJson($uri, array $query = [], $method = 'get', $body = null, array $headers = [])
    {
        $raw = $this->request($uri, $query, $method, $body, $headers);

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \Exception($this->getClassName() . ' 响应不是合法JSON: ' . substr($raw, 0, 500));
        }
        return $data;
    }

    /**
     * 以 multipart/form-data 上传文件
     * @param string $uri
     * @param array $query 查询参数
     * @param array $fields 普通表单字段
     * @param string $fileField 文件字段名
     * @param string $file 本地文件绝对路径
     * @param string $filename 上传后的文件名
     * @param array $headers
     * @return string 原始响应体
     * @throws \Exception
     */
    protected function requestUpload($uri, array $query, array $fields, $fileField, $file, $filename, array $headers = [])
    {
        if (!is_file($file)) {
            throw new \Exception("本地文件不存在: {$file}");
        }

        $url = $this->buildUrl($uri, $query);

        trace(sprintf('[%s] POST(upload) %s file=%s', $this->getClassName(), $url, $filename), 'debug', 'nas');

        $End = null;
        if (function_exists('http_tracker')) {
            $End = http_tracker($this->getClassName() . ':upload', [
                'method' => 'POST',
                'url'    => $url,
                'file'   => $filename,
            ]);
        }

        try {
            list($status, $result) = $this->sendMultipart($url, $fields, $fileField, $file, $filename, $headers);
            is_callable($End) && $End($result, $status);
            return $result;
        } catch (\Exception $e) {
            trace($this->getClassName() . ' NAS上传异常: ' . $e->getMessage() . ' url=' . $url, 'error');
            is_callable($End) && $End($e->getMessage(), $e->getCode());
            throw $e;
        }
    }

    /**
     * 流式发送 multipart/form-data 请求
     *
     * 不用 HttpClient 的 addFile/addData：
     *   1. addData 的语义是“从内存添加上传文件”，用它提交普通参数会生成带 filename
     *      的文件段，服务端读不到该参数；
     *   2. HttpClient 对字符串 body 需要先在内存拼出整个 body，大文件会直接撑爆内存；
     *   3. setHeader 默认把 header 值转小写，会破坏 boundary。
     * 这里按 RFC 1867 自行拼协议头，文件分块读取后直接写 socket，
     * 内存占用与文件大小无关，且保证二进制段位于 body 末尾。
     * @param string $url 完整请求地址
     * @param array $fields 普通表单字段
     * @param string $fileField 文件字段名
     * @param string $file 本地文件绝对路径
     * @param string $filename 上传后的文件名
     * @param array $headers 额外请求头
     * @return array [状态码, 响应体]
     * @throws \Exception
     */
    protected function sendMultipart($url, array $fields, $fileField, $file, $filename, array $headers = [])
    {
        $parts = parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            throw new \Exception('无效的请求地址: ' . $url);
        }

        $ssl = $parts['scheme'] === 'https';
        $port = $parts['port'] ?? ($ssl ? 443 : 80);
        $requestUri = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        $boundary = '----EasySwooleNas' . bin2hex(random_bytes(16));

        // 文件段之前的所有普通字段
        $head = '';
        foreach ($fields as $key => $value) {
            if (is_null($value)) {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $head .= '--' . $boundary . "\r\n";
            $head .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
            $head .= (string)$value . "\r\n";
        }

        // 二进制文件段的段头与 body 结尾
        $fileHead = '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="' . $fileField . '"; filename="' . $filename . '"' . "\r\n"
            . 'Content-Type: application/octet-stream' . "\r\n\r\n";
        $tail = "\r\n" . '--' . $boundary . "--\r\n";

        $fileSize = filesize($file);
        if ($fileSize === false) {
            throw new \Exception("本地文件大小读取失败: {$file}");
        }
        $contentLength = strlen($head) + strlen($fileHead) + $fileSize + strlen($tail);

        $request = 'POST ' . $requestUri . " HTTP/1.1\r\n";
        $request .= 'Host: ' . $parts['host'] . ':' . $port . "\r\n";
        $request .= 'Content-Type: multipart/form-data; boundary=' . $boundary . "\r\n";
        $request .= 'Content-Length: ' . $contentLength . "\r\n";
        // 以连接关闭作为响应结束标志，避免额外处理 keep-alive
        $request .= "Connection: close\r\n";
        $request .= "Accept: */*\r\n";
        foreach ($headers as $key => $value) {
            $request .= $key . ': ' . $value . "\r\n";
        }
        $request .= "\r\n";

        $client = new \Swoole\Coroutine\Client($ssl ? SWOOLE_TCP | SWOOLE_SSL : SWOOLE_TCP);
        $client->set([
            'timeout'          => $this->readTimeout,
            'connect_timeout'  => $this->connectTimeout,
            'ssl_verify_peer'  => $this->sslVerify,
            'ssl_allow_self_signed' => !$this->sslVerify,
        ]);

        if (!$client->connect($parts['host'], $port, $this->connectTimeout)) {
            throw new \Exception(sprintf(
                '%s 连接失败 [errCode=%d errMsg=%s] %s:%d',
                $this->getClassName(), $client->errCode, swoole_strerror($client->errCode), $parts['host'], $port
            ));
        }

        try {
            $this->sendAll($client, $request . $head . $fileHead);

            $fp = fopen($file, 'rb');
            if ($fp === false) {
                throw new \Exception("本地文件打开失败: {$file}");
            }

            try {
                while (!feof($fp)) {
                    $chunk = fread($fp, 1048576);
                    if ($chunk === false) {
                        throw new \Exception("本地文件读取失败: {$file}");
                    }
                    if ($chunk !== '') {
                        $this->sendAll($client, $chunk);
                    }
                }
            } finally {
                fclose($fp);
            }

            $this->sendAll($client, $tail);

            // Connection: close 下服务端发完即断开，recv 返回空串表示结束
            $raw = '';
            while (true) {
                $data = $client->recv($this->readTimeout);
                if ($data === '' || $data === false) {
                    break;
                }
                $raw .= $data;
            }

            if ($raw === '') {
                throw new \Exception(sprintf(
                    '%s 上传无响应 [errCode=%d errMsg=%s]',
                    $this->getClassName(), $client->errCode, swoole_strerror($client->errCode)
                ));
            }
        } finally {
            $client->close();
        }

        return $this->parseHttpResponse($raw);
    }

    /**
     * 确保数据全部写入 socket
     * @param \Swoole\Coroutine\Client $client
     * @param string $data
     * @return void
     * @throws \Exception
     */
    protected function sendAll($client, $data)
    {
        $total = strlen($data);
        $sent = 0;
        while ($sent < $total) {
            $len = $client->send(substr($data, $sent));
            if ($len === false || $len <= 0) {
                throw new \Exception(sprintf(
                    '%s 上传写入失败 [errCode=%d errMsg=%s]',
                    $this->getClassName(), $client->errCode, swoole_strerror($client->errCode)
                ));
            }
            $sent += $len;
        }
    }

    /**
     * 拆分原始 HTTP 响应
     * @param string $raw
     * @return array [状态码, 响应体]
     * @throws \Exception
     */
    protected function parseHttpResponse($raw)
    {
        $pos = strpos($raw, "\r\n\r\n");
        if ($pos === false) {
            throw new \Exception($this->getClassName() . ' 响应格式异常: ' . substr($raw, 0, 500));
        }

        $head = substr($raw, 0, $pos);
        $body = substr($raw, $pos + 4);

        $status = 0;
        if (preg_match('#^HTTP/\d\.\d\s+(\d{3})#', $head, $match)) {
            $status = (int)$match[1];
        }

        // nginx 可能以分块传输返回 JSON
        if (stripos($head, 'Transfer-Encoding: chunked') !== false) {
            $body = $this->dechunk($body);
        }

        return [$status, $body];
    }

    /**
     * 解码 chunked 响应体
     * @param string $body
     * @return string
     */
    protected function dechunk($body)
    {
        $result = '';
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $lineEnd = strpos($body, "\r\n", $offset);
            if ($lineEnd === false) {
                break;
            }

            $size = hexdec(trim(strtok(substr($body, $offset, $lineEnd - $offset), ';')));
            if ($size <= 0) {
                break;
            }

            $result .= substr($body, $lineEnd + 2, $size);
            $offset = $lineEnd + 2 + $size + 2;
        }

        return $result;
    }

    /**
     * 取回二进制响应体，同时带出响应头
     *
     * 与 request() 的区别是会把响应头一起返回，调用方要靠 content-type
     * 判断拿到的到底是文件内容还是 NAS 的 JSON 错误体。
     * 内容会完整进内存，只用于小文件和缩略图。
     * @param string $uri
     * @param array $query
     * @param array $headers
     * @return array [状态码, 响应头, 响应体]
     * @throws \Exception
     */
    protected function requestContent($uri, array $query = [], array $headers = [])
    {
        $url = $this->buildUrl($uri, $query);

        trace(sprintf('[%s] GET(content) %s', $this->getClassName(), $url), 'debug', 'nas');

        $End = null;
        if (function_exists('http_tracker')) {
            $End = http_tracker($this->getClassName() . ':content', [
                'method' => 'GET',
                'url'    => $url,
            ]);
        }

        try {
            $client = $this->newClient($uri, $query);
            if ($headers) {
                $client->setHeaders($headers, true, false);
            }

            $response = $client->get();

            $errCode = $response->getErrCode();
            if ($errCode !== 0) {
                throw new \Exception(sprintf(
                    '%s 请求失败 [Swoole errCode=%d errMsg=%s] url=%s',
                    $this->getClassName(), $errCode, $response->getErrMsg(), $url
                ));
            }

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new \Exception($this->getClassName() . " 请求失败 HTTP {$status}: {$url}");
            }

            $body = (string)$response->getBody();

            // 二进制内容不进日志，只记长度
            is_callable($End) && $End('bytes=' . strlen($body), $status);
            return [$status, (array)$response->getHeaders(), $body];
        } catch (\Exception $e) {
            trace($this->getClassName() . ' NAS读取异常: ' . $e->getMessage() . ' url=' . $url, 'error');
            is_callable($End) && $End($e->getMessage(), $e->getCode());
            throw $e;
        }
    }

    /**
     * 判断响应体是否为 NAS 返回的 JSON 错误
     *
     * 不能只看 content-type 是不是 JSON —— 下载一个真实的 .json 文件时
     * content-type 同样是 application/json。这里进一步要求 JSON 结构
     * 长得像接口的错误信封（带 success=false 或非成功的 status）才算错误。
     * @param string $contentType
     * @param string $body
     * @return array|null 命中时返回解析后的错误信封，否则返回 null
     */
    protected function detectJsonError($contentType, $body)
    {
        if (stripos($contentType, 'json') === false || $body === '') {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }

        if (array_key_exists('success', $data) && $data['success'] === false) {
            return $data;
        }
        if (array_key_exists('status', $data) && (int)$data['status'] !== 1) {
            return $data;
        }
        return null;
    }

    /**
     * 流式下载至本地文件，不在内存中拼接 body，支持超大文件
     * @param string $uri
     * @param array $query
     * @param string $saveFile 本地保存绝对路径
     * @param array $headers
     * @return bool
     * @throws \Exception
     */
    protected function requestDownload($uri, array $query, $saveFile, array $headers = [])
    {
        $dir = dirname($saveFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \Exception("本地目录创建失败: {$dir}");
        }

        $url = $this->buildUrl($uri, $query);

        trace(sprintf('[%s] GET(download) %s save=%s', $this->getClassName(), $url, $saveFile), 'debug', 'nas');

        $End = null;
        if (function_exists('http_tracker')) {
            $End = http_tracker($this->getClassName() . ':download', [
                'method' => 'GET',
                'url'    => $url,
                'save'   => $saveFile,
            ]);
        }

        try {
            $client = $this->newClient($uri, $query);
            if ($headers) {
                $client->setHeaders($headers, true, false);
            }

            $response = $client->download($saveFile);
            if ($response === false) {
                throw new \Exception($this->getClassName() . " 下载失败: {$url}");
            }

            $code = $response->getStatusCode();
            if ($code < 200 || $code >= 300) {
                is_file($saveFile) && unlink($saveFile);
                throw new \Exception($this->getClassName() . " 下载失败 HTTP {$code}: {$url}");
            }

            // 部分 NAS 失败时返回 200 + JSON 错误体。但下载真实的 .json 文件时
            // content-type 也是 JSON，故需进一步确认内容像不像错误信封。
            $contentType = $response->getHeaders()['content-type'] ?? '';
            if (stripos($contentType, 'json') !== false && is_file($saveFile) && filesize($saveFile) <= 65536) {
                $raw = (string)file_get_contents($saveFile);
                if ($this->detectJsonError($contentType, $raw) !== null) {
                    unlink($saveFile);
                    throw new \Exception($this->getClassName() . ' 下载失败: ' . substr($raw, 0, 500));
                }
            }

            is_callable($End) && $End('success', 200);
            return true;
        } catch (\Exception $e) {
            trace($this->getClassName() . ' NAS下载异常: ' . $e->getMessage() . ' url=' . $url, 'error');
            is_callable($End) && $End($e->getMessage(), $e->getCode());
            throw $e;
        }
    }

    /**
     * 统一目录项结构，屏蔽各厂商字段差异
     * @param string $name 文件名
     * @param string $path 绝对路径
     * @param bool $isDir 是否目录
     * @param int $size 字节数
     * @param int $mtime 修改时间戳
     * @param array $raw 厂商原始数据
     * @return array
     */
    protected function formatEntry($name, $path, $isDir, $size = 0, $mtime = 0, array $raw = [])
    {
        return [
            'name'  => $name,
            'path'  => $path,
            'isDir' => (bool)$isDir,
            'size'  => (int)$size,
            'mtime' => (int)$mtime,
            'raw'   => $raw,
        ];
    }

    /**
     * 兼容协程与非协程环境的 sleep
     * @param float $seconds
     * @return void
     */
    protected function sleep($seconds)
    {
        if (class_exists('\Swoole\Coroutine') && \Swoole\Coroutine::getCid() > 0) {
            \Swoole\Coroutine::sleep($seconds);
            return;
        }
        usleep((int)($seconds * 1000000));
    }

    /**
     * 未实现能力时统一抛出
     * @param string $ability
     * @throws \Exception
     */
    protected function unsupported($ability)
    {
        throw new \Exception($this->getClassName() . " 不支持 {$ability} 操作，请改用 SFTP/SMB/WebDAV");
    }

    /**
     * 默认重命名实现：拆出父目录后交由厂商实现
     * @param string $path
     * @param string $newName
     * @param array $options
     * @return bool
     */
    public function rename($path, $newName, $options = [])
    {
        $this->unsupported('rename');
    }

    /**
     * 默认读取实现：厂商未实现时统一抛出
     * @param string $path
     * @param array $options
     * @return string
     */
    public function read($path, $options = [])
    {
        $this->unsupported('read');
    }

    /**
     * 默认缩略图实现：厂商未实现时统一抛出
     * @param string $path
     * @param array $options
     * @return string
     */
    public function thumbnail($path, $options = [])
    {
        $this->unsupported('thumbnail');
    }

    /**
     * 默认预览地址实现：厂商未实现时统一抛出
     * @param string $path
     * @param array $options
     * @return string
     */
    public function previewUrl($path, $options = [])
    {
        $this->unsupported('previewUrl');
    }
}
