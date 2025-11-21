<?php

namespace Tests\Common;

use PHPUnit\Framework\TestCase;

class Functions extends TestCase
{
    /**
     * php easyswoole es-phpunit -mode=xx.admin.dev Tests/Common/Functions.php --filter=testCtx
     * @return void
     */
    public function testCtx()
    {
        $key = 'Joyboo';
        $value = 'Hello World!';

        go(function () use ($key, $value) {

            ctx_set($key, $value);

            $v1 = ctx_get($key);
            var_dump($v1, '==$v1');
        });

        $v2 = ctx_get($key);
        var_dump($v2, '==$v2');
        $this->assertEmpty($v2);

        go(function () use ($key) {
            $v3 = ctx_get($key);
            var_dump($v3, '==$v3');
        });
    }
}
