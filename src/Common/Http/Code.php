<?php

namespace BasicHub\EsCore\Common\Http;

use EasySwoole\Http\Message\Status;

class Code extends Status
{
    // 基本错误类型
    const ERROR_OTHER = 1000;

    // 温柔刷新
    const VERSION_LATER = 1001;
    // 强制刷新
    const VERSION_FORCE = 1002;

    // 没有为评分的会话complete
    const NO_COMPLETE_TOPIC = 1100;

    // jwt token无效
    const JWT_INVALID = 2000;
    // jwt token过期
    const JWT_EXPIRED = 2001;
    // jwt 其他异常
    const JWT_OTHER = 2003;
}
