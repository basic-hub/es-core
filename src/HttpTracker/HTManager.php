<?php

namespace BasicHub\EsCore\HttpTracker;

use EasySwoole\Tracker\PointContext;
use EasySwoole\Tracker\SaveHandlerInterface;

class HTManager extends PointContext
{
    public function __construct(Config $config)
    {
        $handleClassName = $config->getSaveHandlerClassName();
        // 可反射限制必须实现SaveHandlerInterface，暂无必要
        if ($handleClassName && class_exists($handleClassName)) {
            $class = new $handleClassName($config);
        } else {
            $class = new SaveHandler($config);
        }

        if ($config->getSaveAuto()) {
            $this->enableAutoSave();
        }

        if ($globalArg = $config->getSaveGlobalArg()) {
            $this->setGlobalArg($globalArg);
        }

        $this->setSaveHandler($class);
    }
}
