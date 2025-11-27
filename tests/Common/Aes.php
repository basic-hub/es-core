<?php

namespace Tests\Common;

use BasicHub\EsCore\Common\Openssl\Aes as CoreAes;
use PHPUnit\Framework\TestCase;

class Aes extends TestCase
{
    /**
     * php easyswoole es-phpunit -mode=xx.sdk.dev Tests/Common/Openssl.php --filter=testAesDecode
     * @return void
     */
    public function testAesDecode()
    {
        $payload = 'qzGaQmUHUrFdULTKIYShp1fEZvrLPcTj5I5whiFFxPKJadCoUGaDcY1QNHGKzbY8MzDviiS0agNZQRrBT/SuHLRgX3rVR5k2JCwvkPUlpO66dFALs2EMi1FX8rBaBvWonVwy/bfC1mt4sHl2R9cbZ8YqG7YQWxo=';
        $aesKey = 'uQyBpBqk+bOJsJfxchFxPD1uG1p3NZdy7GXa+l2pF/0=';

        $CoreAes = new CoreAes([
            'secret' => $aesKey
        ]);

        $data = $CoreAes->decrypt($payload);
        var_dump($data, '====$data');
        $this->assertNotEmpty($data);
    }

    /**
     * php easyswoole es-phpunit -mode=xx.sdk.dev Tests/Common/Openssl.php --filter=testAesEncode
     * @return void
     * @throws \Exception
     */
    public function testAesEncode()
    {
        $data = ['shuai' => 'Joyboo'];
        $aesKey = '123456';

        $CoreAes = new CoreAes([
            'secret' => $aesKey
        ]);
        $encode = $CoreAes->encrypt(json_encode($data));
        var_dump($encode, '===encode');
        $this->assertNotEmpty($encode);

        $decode = $CoreAes->decrypt($encode);
        var_dump($decode, '====decode');
        $this->assertNotEmpty($decode);
        $arr = json_decode($decode, true);
        $this->assertEquals($data['shuai'], $arr['shuai']);
    }
}
