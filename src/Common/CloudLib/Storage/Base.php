<?php

namespace BasicHub\EsCore\Common\CloudLib\Storage;

use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements StorageInterface
{
    protected function getClassName()
    {
        $arr = explode('\\', static::class);
        return end($arr);
    }

    public function rename($formKey, $toKey, $options = [])
    {
        $this->copy($formKey, $toKey, $options);
        $this->delete($formKey, $options);
    }
}
