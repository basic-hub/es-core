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
     * 兼容Response的相关方法
     * 将响应体 json_decode 为数组，解析失败返回 null
     * @param bool $assoc true-array , false-stdClass
     * @return array|null
     */
    public function json($assoc = true)
    {
        return json_decode($this->raw, $assoc);
    }

    /**
     * 兼容Response的相关方法
     * @return int
     */
    public function getStatusCode()
    {
        return $this->code;
    }

    /**
     * 将响应体解析为 XML 数组，解析失败返回空数组
     * @document  https://www.w3.org/TR/2008/REC-xml-20081126/#charsets
     */
    public function xml(): array
    {
        // libxml_disable_entity_loader 在 PHP 8.0 中已废弃并移除，8.0+ 默认禁用外部实体加载
        if (function_exists('libxml_disable_entity_loader')) {
            $backup = libxml_disable_entity_loader(true);
        }

        // 确保是合法 UTF-8，非 UTF-8 编码先转换，避免 preg_replace /u 返回 null
        $raw = $this->raw;
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8');
        }
        // 过滤 XML 规范不允许的控制字符
        $str = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]+/u', '', $raw);

        libxml_use_internal_errors(true);
        $result = simplexml_load_string((string)$str, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_NOCDATA | LIBXML_NOBLANKS);
        libxml_clear_errors();

        if (isset($backup)) {
            libxml_disable_entity_loader($backup);
        }
        if (!$result) {
            return [];
        }
        $arr = json_decode(json_encode($result), true);
        return is_array($arr) ? $arr : [];
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
