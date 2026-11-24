<?php

namespace BasicHub\EsCore\Common\CloudLib\Notify;

interface MessageInterface
{
    /**
     * 获取完整的消息数据结构
     * @return array
     */
    public function fullData();
}
