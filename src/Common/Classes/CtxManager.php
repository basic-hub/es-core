<?php

namespace BasicHub\EsCore\Common\Classes;

use EasySwoole\Component\Context\ContextManager;
use EasySwoole\Http\Request;
use EasySwoole\Socket\Bean\Caller;

/**
 * 协程上下文管理器，不仅限于Http场景
 * 内置一些快捷操作方法，也可直接调用父类方法
 */
class CtxManager extends ContextManager
{
    private $httpRequestKey = 'httpRequest';

    private $websocketCallerKey = 'websocketCaller';

    private $adminOperinfoKey = 'adminOperinfo';

    private $isRsaKey = 'isRsa';

    public function setRequest(Request $request)
    {
        $this->set($this->httpRequestKey, $request);
    }

    public function getRequest(): Request
    {
        return $this->get($this->httpRequestKey);
    }

    public function setCaller(Caller $caller)
    {
        $this->set($this->websocketCallerKey, $caller);
    }

    public function getCaller(): Caller
    {
        return $this->get($this->websocketCallerKey);
    }

    public function setOperinfo(array $operinfo)
    {
        $this->set($this->adminOperinfoKey, $operinfo);
    }

    /**
     * @return array
     */
    public function getOperinfo()
    {
        return $this->get($this->adminOperinfoKey);
    }

    public function setIsRsa(bool $isRsa)
    {
        $this->set($this->isRsaKey, $isRsa);
    }

    /**
     * @return bool
     */
    public function getIsRsa()
    {
        return $this->get($this->isRsaKey);
    }
}
