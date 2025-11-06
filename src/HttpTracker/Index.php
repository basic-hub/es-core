<?php

namespace BasicHub\EsCore\HttpTracker;

use EasySwoole\Component\Di;
use EasySwoole\Tracker\PointContext;

class Index extends PointContext
{
    const HTTP_TRACKER_HANDLER_CLASS_NAME = 'HTTP_TRACKER_HANDLER_CLASS_NAME';

    public function __construct(array $handleConfig = [])
    {
        $handleClassName = Di::getInstance()->get(self::HTTP_TRACKER_HANDLER_CLASS_NAME);
        if (empty($handleClassName) || !class_exists($handleClassName)) {
            $class = new SaveHandler($handleConfig);
        } else {
            $class = new $handleClassName($handleConfig);
        }
        $this->enableAutoSave()->setSaveHandler($class);
    }
}
