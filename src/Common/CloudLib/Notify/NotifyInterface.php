<?php

namespace BasicHub\EsCore\Common\CloudLib\Notify;

interface NotifyInterface
{
    /**
     * 发送消息
     * @param MessageInterface $message
     * @return mixed
     */
    public function send(MessageInterface $message);
}
