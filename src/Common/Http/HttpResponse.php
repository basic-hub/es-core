<?php

namespace BasicHub\EsCore\Common\Http;

use \EasySwoole\HttpClient\Bean\Response;

/**
 * HTTP 响应值对象
 *
 * request() 始终返回此对象，由调用方自主决定如何解析响应体、处理状态码。
 *
 * 用法示例:
 *   $resp = hcurl('https://api.example.com/data', ['id' => 1]);
 *   $code = $resp->code;     // http响应码
 *   $arr = $resp->json();    // json_decode 后的数组
 *   $raw = $resp->raw;       // 原始响应字符串
 *   $str = $resp->body();    // 原始响应字符串
 *   $xml = $resp->xml();     // 解析 XML 为数组
 *   $obj = $resp->getObject(); // 原始响应对象（仅 HCurl 有效，Curl 返回 null）
 */
class HttpResponse
{
    /**
     * HTTP 状态码，网络异常时为 0
     * @var int
     */
    public $code;

    /**
     * 原始响应字符串
     * @var string
     */
    public $raw;

    /**
     * 原始响应对象（仅 HCurl 有效）
     * @var Response|null
     */
    private $object;

    /**
     * @param int    $code
     * @param string $raw
     * @param mixed  $object
     */
    public function __construct($code, $raw, $object = null)
    {
        $this->code   = $code;
        $this->raw    = $raw;
        $this->object = $object;
    }

    /**
     * 返回原始响应字符串
     */
    public function body()
    {
        return $this->raw;
    }

    /**
     * 将响应体 json_decode 为数组，解析失败返回 null
     * @return array|null
     */
    public function json()
    {
        return json_decode($this->raw, true);
    }

    /**
     * 将响应体解析为 XML 数组，解析失败返回空数组
     * @see https://www.w3.org/TR/2008/REC-xml-20081126/#charsets
     */
    public function xml(): array
    {
        $backup = libxml_disable_entity_loader(true);
        $str    = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]+/u', '', $this->raw);
        $result = simplexml_load_string($str, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_NOCDATA | LIBXML_NOBLANKS);
        libxml_disable_entity_loader($backup);
        return $result ? (array)$result : [];
    }

    /**
     * 返回原始响应对象，仅 HCurl 有效；Curl 或网络异常时返回 null
     * @return Response|null
     */
    public function getObject()
    {
        return ($this->object instanceof Response) ? $this->object : null;
    }

    /**
     * 直接当字符串使用时返回原始响应体
     */
    public function __toString(): string
    {
        return $this->raw;
    }
}
