<?php

namespace BasicHub\EsCore\Notify\Interfaces;

interface NotifyInterface
{
    public function does(MessageInterface $message);
}
