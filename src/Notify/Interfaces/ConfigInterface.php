<?php

namespace BasicHub\EsCore\Notify\Interfaces;

interface ConfigInterface
{
    public function getNotifyClass(): NotifyInterface;
}
