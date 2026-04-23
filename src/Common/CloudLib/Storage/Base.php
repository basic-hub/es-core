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

    /**
     * @param string $key
     * @return string
     */
    protected function getObjectKet($key)
    {
        return $key;
    }

    public function rename($formKey, $toKey, $options = [])
    {
        $formKey = $this->getObjectKet($formKey);
        $toKey = $this->getObjectKet($toKey);

        $this->copy($formKey, $toKey, $options);
        $this->delete($formKey, $options);
    }
}
