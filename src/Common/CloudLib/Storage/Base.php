<?php

namespace BasicHub\EsCore\Common\CloudLib\Storage;

use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements StorageInterface
{
    protected function getClassName()
    {
        $arr = explode('\\', static::class);
        $end = end($arr);
        return strtolower($end);
    }
}
