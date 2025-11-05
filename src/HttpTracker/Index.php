<?php

namespace BasicHub\EsCore\HttpTracker;

use EasySwoole\Component\Di;
use EasySwoole\Tracker\PointContext;

class Index extends PointContext
{
    const HANDLER_CLASS_NAME = 'HANDLER_CLASS_NAME';

    public function __construct(array $handleConfig = [])
    {
        $handleClassName = Di::getInstance()->get(self::HANDLER_CLASS_NAME);
        if (empty($handleClassName)) {
            $class = new SaveHandler($handleConfig);
        } else {
            $class = new $handleClassName($handleConfig);
        }
        $this->enableAutoSave()->setSaveHandler($class);
    }
}
