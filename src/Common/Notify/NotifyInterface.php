<?php

namespace BasicHub\EsCore\Common\Notify;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;

interface NotifyInterface
{
    public function send(MessageInterface $message);
}
