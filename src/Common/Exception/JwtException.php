<?php

namespace BasicHub\EsCore\Common\Exception;

use BasicHub\EsCore\Common\Http\Code;

class JwtException extends \Exception
{
    public function __construct($message = "", $code = Code::JWT_OTHER, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
