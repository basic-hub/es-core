<?php

namespace BasicHub\EsCore\Common\Classes;


use EasySwoole\Component\CoroutineSingleTon;
use EasySwoole\Http\Request;
use EasySwoole\Socket\Bean\Caller;
use Swoole\Coroutine;

/**
 * 通用协程单例对象
 * Class MyCoroutine
 * @package App\Common\Classes
 */
class CtxRequest
{
    use CoroutineSingleTon;

    /**
     * Request对象
     * @var Request|null
     */
    protected $request = null;

    /**
     * @var null | Caller
     */
    protected $caller = null;

    protected $operinfo = [];

    /**
     * 标记是否经过rsa解密，用于一些不是非常严格要求rsa加密场景，如果需要严格判断rsa加密，则应该直接从控制器的rsa属性中获取请求参数
     * @var bool
     */
    protected $isrsa = false;

    public function setIsrsa(bool $isrsa)
    {
        $this->isrsa = $isrsa;
    }

    public function getIsrsa()
    {
        return $this->isrsa;
    }

    public function getOperinfo(): array
    {
        return $this->operinfo;
    }

    public function withOperinfo(array $operinfo = []): void
    {
        if ($this->request instanceof Request) {
            $this->request->withAttribute('operinfo', $operinfo);
        }
        $this->operinfo = $operinfo;
    }

    /*************** 协程内判断方法，备选方案，在不方便调$server->connection_info($fd);的场景使用 *************/
    public function isHttp(): bool
    {
        return $this->request instanceof Request;
    }

    public function isWebSocket(): bool
    {
        return $this->caller instanceof Caller;
    }

    public function __set($name, $value)
    {
        $name = strtolower($name);
        $this->{$name} = $value;
    }

    public function __get($name)
    {
        $name = strtolower($name);
        if (property_exists($this, $name)) {
            return $this->{$name};
        } else {
            $cid = Coroutine::getCid();
            throw new \Exception("[cid:{$cid}]CtxRequest Not Exists Protected: $name");
        }
    }
}
