<?php

namespace Tests\Common;

use BasicHub\EsCore\Common\CloudLib\Geo\Iso3166;
use BasicHub\EsCore\Common\CloudLib\Geo\Base;
use BasicHub\EsCore\Common\CloudLib\Geo\Geo;
use PHPUnit\Framework\TestCase;

/**
 * ISO 3166-1 国家代码转换测试
 *
 * php easyswoole phpunit tests/Common/GeoCode.php
 */
class GeoCode extends TestCase
{
    public function testAlpha2ToAlpha3()
    {
        $this->assertEquals('CHN', Iso3166::alpha2ToAlpha3('CN'));
        $this->assertEquals('USA', Iso3166::alpha2ToAlpha3('US'));
        $this->assertEquals('TWN', Iso3166::alpha2ToAlpha3('TW'));
        $this->assertEquals('HKG', Iso3166::alpha2ToAlpha3('HK'));
        $this->assertEquals('MAC', Iso3166::alpha2ToAlpha3('MO'));
        $this->assertEquals('JPN', Iso3166::alpha2ToAlpha3('JP'));
        $this->assertEquals('GBR', Iso3166::alpha2ToAlpha3('GB'));
    }

    public function testAlpha2ToAlpha3CaseInsensitive()
    {
        $this->assertEquals('CHN', Iso3166::alpha2ToAlpha3('cn'));
        $this->assertEquals('USA', Iso3166::alpha2ToAlpha3('us'));
        $this->assertEquals('CHN', Iso3166::alpha2ToAlpha3(' Cn '));
    }

    public function testAlpha2ToAlpha3NotFound()
    {
        $this->assertNull(Iso3166::alpha2ToAlpha3('AA'));
        $this->assertNull(Iso3166::alpha2ToAlpha3(''));
        $this->assertNull(Iso3166::alpha2ToAlpha3('ABC'));
    }

    public function testAlpha2ToAlpha3FailMarker()
    {
        // ZZ => ZZZ 是解析失败标识，应能互转
        $this->assertEquals('ZZZ', Iso3166::alpha2ToAlpha3('ZZ'));
    }

    public function testAlpha3ToAlpha2()
    {
        $this->assertEquals('CN', Iso3166::alpha3ToAlpha2('CHN'));
        $this->assertEquals('US', Iso3166::alpha3ToAlpha2('USA'));
        $this->assertEquals('TW', Iso3166::alpha3ToAlpha2('TWN'));
        $this->assertEquals('HK', Iso3166::alpha3ToAlpha2('HKG'));
        $this->assertEquals('MO', Iso3166::alpha3ToAlpha2('MAC'));
        $this->assertEquals('JP', Iso3166::alpha3ToAlpha2('JPN'));
        $this->assertEquals('GB', Iso3166::alpha3ToAlpha2('GBR'));
    }

    public function testAlpha3ToAlpha2CaseInsensitive()
    {
        $this->assertEquals('CN', Iso3166::alpha3ToAlpha2('chn'));
        $this->assertEquals('US', Iso3166::alpha3ToAlpha2(' Chn '));
    }

    public function testAlpha3ToAlpha2NotFound()
    {
        $this->assertNull(Iso3166::alpha3ToAlpha2('AAA'));
        $this->assertNull(Iso3166::alpha3ToAlpha2(''));
    }

    public function testAlpha3ToAlpha2FailMarker()
    {
        // ZZZ => ZZ 是解析失败标识，应能互转
        $this->assertEquals('ZZ', Iso3166::alpha3ToAlpha2('ZZZ'));
    }

    public function testCnNameToAlpha2()
    {
        // 中国及港澳台（convAreaMap 处理后的名称）
        $this->assertEquals('CN', Iso3166::cnNameToAlpha2('中国大陆'));
        $this->assertEquals('TW', Iso3166::cnNameToAlpha2('中国台湾'));
        $this->assertEquals('HK', Iso3166::cnNameToAlpha2('中国香港'));
        $this->assertEquals('MO', Iso3166::cnNameToAlpha2('中国澳门'));

        // 兼容未处理原始名称
        $this->assertEquals('CN', Iso3166::cnNameToAlpha2('中国'));
        $this->assertEquals('TW', Iso3166::cnNameToAlpha2('台湾'));

        // 常见国家
        $this->assertEquals('US', Iso3166::cnNameToAlpha2('美国'));
        $this->assertEquals('JP', Iso3166::cnNameToAlpha2('日本'));
        $this->assertEquals('KR', Iso3166::cnNameToAlpha2('韩国'));
        $this->assertEquals('GB', Iso3166::cnNameToAlpha2('英国'));
        $this->assertEquals('FR', Iso3166::cnNameToAlpha2('法国'));
        $this->assertEquals('DE', Iso3166::cnNameToAlpha2('德国'));
        $this->assertEquals('RU', Iso3166::cnNameToAlpha2('俄罗斯'));
        $this->assertEquals('RU', Iso3166::cnNameToAlpha2('俄罗斯联邦'));
        $this->assertEquals('BD', Iso3166::cnNameToAlpha2('孟加拉'));
        $this->assertEquals('BD', Iso3166::cnNameToAlpha2('孟加拉国'));
        $this->assertEquals('CI', Iso3166::cnNameToAlpha2('科特迪瓦'));
        $this->assertEquals('CI', Iso3166::cnNameToAlpha2('象牙海岸'));
        $this->assertEquals('MK', Iso3166::cnNameToAlpha2('北马其顿'));
        $this->assertEquals('MK', Iso3166::cnNameToAlpha2('前南马其顿'));
        $this->assertEquals('CD', Iso3166::cnNameToAlpha2('刚果民主共和国'));
        $this->assertEquals('CD', Iso3166::cnNameToAlpha2('扎伊尔'));
        $this->assertEquals('BA', Iso3166::cnNameToAlpha2('波黑'));
        $this->assertEquals('BA', Iso3166::cnNameToAlpha2('波斯尼亚和黑塞哥维那'));
        $this->assertEquals('DO', Iso3166::cnNameToAlpha2('多米尼加'));
        $this->assertEquals('DO', Iso3166::cnNameToAlpha2('多米尼加共和国'));
    }

    public function testCnNameToAlpha2NotFound()
    {
        $this->assertNull(Iso3166::cnNameToAlpha2('不存在的国家'));
        $this->assertNull(Iso3166::cnNameToAlpha2(''));
    }

    public function testCnNameToAlpha3()
    {
        $this->assertEquals('CHN', Iso3166::cnNameToAlpha3('中国大陆'));
        $this->assertEquals('TWN', Iso3166::cnNameToAlpha3('中国台湾'));
        $this->assertEquals('USA', Iso3166::cnNameToAlpha3('美国'));
        $this->assertEquals('JPN', Iso3166::cnNameToAlpha3('日本'));
        $this->assertEquals('RUS', Iso3166::cnNameToAlpha3('俄罗斯'));
    }

    public function testCnNameToAlpha3NotFound()
    {
        $this->assertNull(Iso3166::cnNameToAlpha3('不存在的国家'));
    }

    /**
     * alpha-2 ↔ alpha-3 双向转换一致性
     */
    public function testBidirectionalConsistency()
    {
        $reflection = new \ReflectionClass(Iso3166::class);
        $map = $reflection->getConstant('ALPHA2_TO_ALPHA3');

        foreach ($map as $alpha2 => $alpha3) {
            $this->assertEquals($alpha2, Iso3166::alpha3ToAlpha2($alpha3), "alpha3($alpha3) should map back to alpha2($alpha2)");
            $this->assertEquals($alpha3, Iso3166::alpha2ToAlpha3($alpha2), "alpha2($alpha2) should map to alpha3($alpha3)");
        }
    }

    /**
     * 测试公共函数 geo_alpha3_to_alpha2
     */
    public function testGeoAlpha3ToAlpha2Function()
    {
        $this->assertEquals('CN', geo_alpha3_to_alpha2('CHN'));
        $this->assertEquals('US', geo_alpha3_to_alpha2('USA'));
        $this->assertEquals('TW', geo_alpha3_to_alpha2('TWN'));
        $this->assertEquals('JP', geo_alpha3_to_alpha2('JPN'));
        $this->assertEquals('GB', geo_alpha3_to_alpha2('GBR'));
        $this->assertEquals('CN', geo_alpha3_to_alpha2('chn'));
        $this->assertEquals('ZZ', geo_alpha3_to_alpha2('ZZZ')); // 失败标识互转
        $this->assertNull(geo_alpha3_to_alpha2('AAA'));
        $this->assertNull(geo_alpha3_to_alpha2(''));
    }

    /**
     * 测试公共函数 geo_alpha2_to_alpha3
     */
    public function testGeoAlpha2ToAlpha3Function()
    {
        $this->assertEquals('CHN', geo_alpha2_to_alpha3('CN'));
        $this->assertEquals('USA', geo_alpha2_to_alpha3('US'));
        $this->assertEquals('TWN', geo_alpha2_to_alpha3('TW'));
        $this->assertEquals('HKG', geo_alpha2_to_alpha3('HK'));
        $this->assertEquals('MAC', geo_alpha2_to_alpha3('MO'));
        $this->assertEquals('JPN', geo_alpha2_to_alpha3('JP'));
        $this->assertEquals('GBR', geo_alpha2_to_alpha3('GB'));
        $this->assertEquals('CHN', geo_alpha2_to_alpha3('cn'));
        $this->assertEquals('CHN', geo_alpha2_to_alpha3(' Cn '));
        $this->assertEquals('ZZZ', geo_alpha2_to_alpha3('ZZ')); // 失败标识互转
        $this->assertNull(geo_alpha2_to_alpha3('AA'));
        $this->assertNull(geo_alpha2_to_alpha3(''));
    }

    /**
     * 测试 FAIL 标准失败常量定义
     */
    public function testFailConstants()
    {
        // Base 中定义
        $this->assertEquals(['未知'], Base::FAIL_AREA);
        $this->assertEquals('未知', Base::FAIL_ISP);
        // Iso3166 中定义
        $this->assertEquals('ZZ', Iso3166::FAIL_ALPHA2);
        $this->assertEquals('ZZZ', Iso3166::FAIL_ALPHA3);
    }

    /**
     * 测试 FAIL_ALPHA2 与 FAIL_ALPHA3 可通过 Iso3166 互转
     */
    public function testFailMarkerBidirectional()
    {
        $this->assertEquals(Iso3166::FAIL_ALPHA3, Iso3166::alpha2ToAlpha3(Iso3166::FAIL_ALPHA2));
        $this->assertEquals(Iso3166::FAIL_ALPHA2, Iso3166::alpha3ToAlpha2(Iso3166::FAIL_ALPHA3));
    }

    /**
     * 测试 alpha2ToCn 默认映射
     */
    public function testAlpha2ToCn()
    {
        $this->assertEquals('中国大陆', Iso3166::alpha2ToCn('CN'));
        $this->assertEquals('中国台湾', Iso3166::alpha2ToCn('TW'));
        $this->assertEquals('中国香港', Iso3166::alpha2ToCn('HK'));
        $this->assertEquals('中国澳门', Iso3166::alpha2ToCn('MO'));
        $this->assertEquals('美国', Iso3166::alpha2ToCn('US'));
        $this->assertEquals('日本', Iso3166::alpha2ToCn('JP'));
        $this->assertEquals('英国', Iso3166::alpha2ToCn('GB'));
        $this->assertEquals('未知', Iso3166::alpha2ToCn('ZZ'));
    }

    /**
     * 测试 alpha2ToCn 大小写不敏感
     */
    public function testAlpha2ToCnCaseInsensitive()
    {
        $this->assertEquals('中国大陆', Iso3166::alpha2ToCn('cn'));
        $this->assertEquals('美国', Iso3166::alpha2ToCn(' Us '));
    }

    /**
     * 测试 alpha2ToCn 未找到
     */
    public function testAlpha2ToCnNotFound()
    {
        $this->assertNull(Iso3166::alpha2ToCn('AA'));
        $this->assertNull(Iso3166::alpha2ToCn(''));
    }

    /**
     * 测试 alpha2ToCn override 覆盖能力
     * 业务A希望 CN 转为"中国"，业务B希望转为"中国大陆"（默认）
     */
    public function testAlpha2ToCnOverride()
    {
        // 默认值
        $this->assertEquals('中国大陆', Iso3166::alpha2ToCn('CN'));

        // 业务A：覆盖为"中国"
        $this->assertEquals('中国', Iso3166::alpha2ToCn('CN', ['CN' => '中国']));

        // 业务B：不传override，仍是默认"中国大陆"
        $this->assertEquals('中国大陆', Iso3166::alpha2ToCn('CN'));

        // 覆盖多个，且不影响其他
        $this->assertEquals('中国', Iso3166::alpha2ToCn('CN', ['CN' => '中国', 'US' => '美利坚']));
        $this->assertEquals('美利坚', Iso3166::alpha2ToCn('US', ['CN' => '中国', 'US' => '美利坚']));
        // 未覆盖的仍用默认
        $this->assertEquals('日本', Iso3166::alpha2ToCn('JP', ['CN' => '中国']));

        // override key 大小写不敏感
        $this->assertEquals('中国', Iso3166::alpha2ToCn('CN', ['cn' => '中国']));
    }

    /**
     * 测试 alpha3ToCn 默认映射
     */
    public function testAlpha3ToCn()
    {
        $this->assertEquals('中国大陆', Iso3166::alpha3ToCn('CHN'));
        $this->assertEquals('中国台湾', Iso3166::alpha3ToCn('TWN'));
        $this->assertEquals('美国', Iso3166::alpha3ToCn('USA'));
        $this->assertEquals('日本', Iso3166::alpha3ToCn('JPN'));
        $this->assertEquals('未知', Iso3166::alpha3ToCn('ZZZ'));
    }

    /**
     * 测试 alpha3ToCn 大小写不敏感
     */
    public function testAlpha3ToCnCaseInsensitive()
    {
        $this->assertEquals('中国大陆', Iso3166::alpha3ToCn('chn'));
        $this->assertEquals('美国', Iso3166::alpha3ToCn(' Usa '));
    }

    /**
     * 测试 alpha3ToCn 未找到
     */
    public function testAlpha3ToCnNotFound()
    {
        $this->assertNull(Iso3166::alpha3ToCn('AAA'));
        $this->assertNull(Iso3166::alpha3ToCn(''));
    }

    /**
     * 测试 alpha3ToCn override 覆盖能力
     */
    public function testAlpha3ToCnOverride()
    {
        // 默认值
        $this->assertEquals('中国大陆', Iso3166::alpha3ToCn('CHN'));

        // 覆盖为"中国"
        $this->assertEquals('中国', Iso3166::alpha3ToCn('CHN', ['CHN' => '中国']));

        // 覆盖多个
        $this->assertEquals('中国', Iso3166::alpha3ToCn('CHN', ['CHN' => '中国', 'USA' => '美利坚']));
        $this->assertEquals('美利坚', Iso3166::alpha3ToCn('USA', ['CHN' => '中国', 'USA' => '美利坚']));

        // override key 大小写不敏感
        $this->assertEquals('中国', Iso3166::alpha3ToCn('CHN', ['chn' => '中国']));
    }

    /**
     * 测试公共函数 geo_alpha2_to_cn
     */
    public function testGeoAlpha2ToCnFunction()
    {
        $this->assertEquals('中国大陆', geo_alpha2_to_cn('CN'));
        $this->assertEquals('美国', geo_alpha2_to_cn('US'));
        $this->assertEquals('中国', geo_alpha2_to_cn('CN', ['CN' => '中国']));
        $this->assertEquals('未知', geo_alpha2_to_cn('ZZ'));
        $this->assertNull(geo_alpha2_to_cn('AA'));
    }

    /**
     * 测试公共函数 geo_alpha3_to_cn
     */
    public function testGeoAlpha3ToCnFunction()
    {
        $this->assertEquals('中国大陆', geo_alpha3_to_cn('CHN'));
        $this->assertEquals('美国', geo_alpha3_to_cn('USA'));
        $this->assertEquals('中国', geo_alpha3_to_cn('CHN', ['CHN' => '中国']));
        $this->assertEquals('未知', geo_alpha3_to_cn('ZZZ'));
        $this->assertNull(geo_alpha3_to_cn('AAA'));
    }

    /**
     * 测试 alpha2ToCn 与 alpha3ToCn 结果一致性
     */
    public function testAlpha2ToCnAndAlpha3ToCnConsistency()
    {
        $reflection = new \ReflectionClass(Iso3166::class);
        $a2a3 = $reflection->getConstant('ALPHA2_TO_ALPHA3');

        foreach ($a2a3 as $alpha2 => $alpha3) {
            $cn2 = Iso3166::alpha2ToCn($alpha2);
            $cn3 = Iso3166::alpha3ToCn($alpha3);
            if ($cn2 !== null && $cn3 !== null) {
                $this->assertEquals($cn2, $cn3, "alpha2($alpha2)->cn and alpha3($alpha3)->cn should match");
            }
        }
    }

    /**
     * 测试快捷函数 geo_alpha2 / geo_alpha3
     * 这两个函数是对 geo($ip, 'alpha2'/'alpha3') 的封装，需要真实IP库才能测试实际解析
     * 这里仅验证函数存在且可调用，实际解析测试需在有IP库的环境下进行
     */
    public function testGeoAlpha2AndGeoAlpha3FunctionsExist()
    {
        $this->assertTrue(function_exists('geo_alpha2'));
        $this->assertTrue(function_exists('geo_alpha3'));

        // 非公网IP应返回 FAIL 标识（不需要真实IP库，isNonPublicIp 在驱动层拦截）
        // 注意：这需要 config('GEO') 配置可用，若无配置会触发 get_drivers 异常被 geo() catch 后返回 null
        // 此处仅验证函数可调用，不断言具体返回值
        $r2 = geo_alpha2('192.168.1.1');
        $r3 = geo_alpha3('192.168.1.1');
        $this->assertTrue($r2 === null || $r2 === Iso3166::FAIL_ALPHA2);
        $this->assertTrue($r3 === null || $r3 === Iso3166::FAIL_ALPHA3);
    }

    /**
     * 测试 Geo 类的 ISO 3166-1 转换方法（不需要 IP 库）
     */
    public function testGeoClassConversions()
    {
        // alpha-2 ↔ alpha-3
        $this->assertEquals('CHN', Geo::alpha2ToAlpha3('CN'));
        $this->assertEquals('CN', Geo::alpha3ToAlpha2('CHN'));
        $this->assertEquals('USA', Geo::alpha2ToAlpha3('US'));
        $this->assertEquals('US', Geo::alpha3ToAlpha2('USA'));

        // alpha-2 / alpha-3 转中文
        $this->assertEquals('中国大陆', Geo::alpha2ToCn('CN'));
        $this->assertEquals('中国大陆', Geo::alpha3ToCn('CHN'));
        $this->assertEquals('美国', Geo::alpha2ToCn('US'));
        $this->assertEquals('美国', Geo::alpha3ToCn('USA'));

        // override
        $this->assertEquals('中国', Geo::alpha2ToCn('CN', ['CN' => '中国']));
        $this->assertEquals('中国', Geo::alpha3ToCn('CHN', ['CHN' => '中国']));

        // 失败标识
        $this->assertEquals('ZZZ', Geo::alpha2ToAlpha3(Iso3166::FAIL_ALPHA2));
        $this->assertEquals('ZZ', Geo::alpha3ToAlpha2(Iso3166::FAIL_ALPHA3));
        $this->assertEquals('未知', Geo::alpha2ToCn(Iso3166::FAIL_ALPHA2));
    }

    /**
     * 测试全局函数与 Geo 类方法结果一致
     */
    public function testGlobalFunctionsMatchGeoClass()
    {
        $this->assertEquals(Geo::alpha3ToAlpha2('CHN'), geo_alpha3_to_alpha2('CHN'));
        $this->assertEquals(Geo::alpha2ToAlpha3('CN'), geo_alpha2_to_alpha3('CN'));
        $this->assertEquals(Geo::alpha2ToCn('CN'), geo_alpha2_to_cn('CN'));
        $this->assertEquals(Geo::alpha3ToCn('CHN'), geo_alpha3_to_cn('CHN'));
        $this->assertEquals(Geo::alpha2ToCn('CN', ['CN' => '中国']), geo_alpha2_to_cn('CN', ['CN' => '中国']));
    }
}
