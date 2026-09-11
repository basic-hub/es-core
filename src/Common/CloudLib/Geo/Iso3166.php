<?php

namespace BasicHub\EsCore\Common\CloudLib\Geo;

/**
 * ISO 3166-1 国家代码工具
 * - alpha-2: 两位字母国家代码 (CN, US, TW)
 * - alpha-3: 三位字母国家代码 (CHN, USA, TWN)
 *
 * 用途：
 * 1. alpha-2 与 alpha-3 互转
 * 2. 中文国家名（经 Geo convAreaMap 处理后的国家级名称）转 alpha-2 / alpha-3
 *
 * @link https://www.iso.org/iso-3166-country-codes.html
 */
class Iso3166
{
    /**
     * 自定义状态码（以 _ 开头，永远不会与 ISO 3166-1 标准码冲突）
     *
     * 解析失败（真正未知，无法定位）：
     *   _ZZ  / _ZZZ — IP 库无记录、格式非法等原因导致的解析失败
     *
     * 私有/保留 IP（已知状态，并非真正未知）：
     *   _LA  / _LAN — 局域网、CGNAT、回环、链路本地等非公网地址
     */
    const FAIL_ALPHA2    = '_ZZ';
    const FAIL_ALPHA3    = '_ZZZ';
    const PRIVATE_ALPHA2 = '_LA';
    const PRIVATE_ALPHA3 = '_LAN';

    /**
     * alpha-2 => alpha-3 完整映射表（ISO 3166-1 官方分配代码）
     * 共 249 个国家/地区
     */
    const ALPHA2_TO_ALPHA3 = [
        'AD' => 'AND', 'AE' => 'ARE', 'AF' => 'AFG', 'AG' => 'ATG',
        'AI' => 'AIA', 'AL' => 'ALB', 'AM' => 'ARM', 'AO' => 'AGO',
        'AQ' => 'ATA', 'AR' => 'ARG', 'AS' => 'ASM', 'AT' => 'AUT',
        'AU' => 'AUS', 'AW' => 'ABW', 'AX' => 'ALA', 'AZ' => 'AZE',
        'BA' => 'BIH', 'BB' => 'BRB', 'BD' => 'BGD', 'BE' => 'BEL',
        'BF' => 'BFA', 'BG' => 'BGR', 'BH' => 'BHR', 'BI' => 'BDI',
        'BJ' => 'BEN', 'BL' => 'BLM', 'BM' => 'BMU', 'BN' => 'BRN',
        'BO' => 'BOL', 'BQ' => 'BES', 'BR' => 'BRA', 'BS' => 'BHS',
        'BT' => 'BTN', 'BV' => 'BVT', 'BW' => 'BWA', 'BY' => 'BLR',
        'BZ' => 'BLZ', 'CA' => 'CAN', 'CC' => 'CCK', 'CD' => 'COD',
        'CF' => 'CAF', 'CG' => 'COG', 'CH' => 'CHE', 'CI' => 'CIV',
        'CK' => 'COK', 'CL' => 'CHL', 'CM' => 'CMR', 'CN' => 'CHN',
        'CO' => 'COL', 'CR' => 'CRI', 'CU' => 'CUB', 'CV' => 'CPV',
        'CW' => 'CUW', 'CX' => 'CXR', 'CY' => 'CYP', 'CZ' => 'CZE',
        'DE' => 'DEU', 'DJ' => 'DJI', 'DK' => 'DNK', 'DM' => 'DMA',
        'DO' => 'DOM', 'DZ' => 'DZA', 'EC' => 'ECU', 'EE' => 'EST',
        'EG' => 'EGY', 'EH' => 'ESH', 'ER' => 'ERI', 'ES' => 'ESP',
        'ET' => 'ETH', 'FI' => 'FIN', 'FJ' => 'FJI', 'FK' => 'FLK',
        'FM' => 'FSM', 'FO' => 'FRO', 'FR' => 'FRA', 'GA' => 'GAB',
        'GB' => 'GBR', 'GD' => 'GRD', 'GE' => 'GEO', 'GF' => 'GUF',
        'GG' => 'GGY', 'GH' => 'GHA', 'GI' => 'GIB', 'GL' => 'GRL',
        'GM' => 'GMB', 'GN' => 'GIN', 'GP' => 'GLP', 'GQ' => 'GNQ',
        'GR' => 'GRC', 'GS' => 'SGS', 'GT' => 'GTM', 'GU' => 'GUM',
        'GW' => 'GNB', 'GY' => 'GUY', 'HK' => 'HKG', 'HM' => 'HMD',
        'HN' => 'HND', 'HR' => 'HRV', 'HT' => 'HTI', 'HU' => 'HUN',
        'ID' => 'IDN', 'IE' => 'IRL', 'IL' => 'ISR', 'IM' => 'IMN',
        'IN' => 'IND', 'IO' => 'IOT', 'IQ' => 'IRQ', 'IR' => 'IRN',
        'IS' => 'ISL', 'IT' => 'ITA', 'JE' => 'JEY', 'JM' => 'JAM',
        'JO' => 'JOR', 'JP' => 'JPN', 'KE' => 'KEN', 'KG' => 'KGZ',
        'KH' => 'KHM', 'KI' => 'KIR', 'KM' => 'COM', 'KN' => 'KNA',
        'KP' => 'PRK', 'KR' => 'KOR', 'KW' => 'KWT', 'KY' => 'CYM',
        'KZ' => 'KAZ', 'LA' => 'LAO', 'LB' => 'LBN', 'LC' => 'LCA',
        'LI' => 'LIE', 'LK' => 'LKA', 'LR' => 'LBR', 'LS' => 'LSO',
        'LT' => 'LTU', 'LU' => 'LUX', 'LV' => 'LVA', 'LY' => 'LYB',
        'MA' => 'MAR', 'MC' => 'MCO', 'MD' => 'MDA', 'ME' => 'MNE',
        'MF' => 'MAF', 'MG' => 'MDG', 'MH' => 'MHL', 'MK' => 'MKD',
        'ML' => 'MLI', 'MM' => 'MMR', 'MN' => 'MNG', 'MO' => 'MAC',
        'MP' => 'MNP', 'MQ' => 'MTQ', 'MR' => 'MRT', 'MS' => 'MSR',
        'MT' => 'MLT', 'MU' => 'MUS', 'MV' => 'MDV', 'MW' => 'MWI',
        'MX' => 'MEX', 'MY' => 'MYS', 'MZ' => 'MOZ', 'NA' => 'NAM',
        'NC' => 'NCL', 'NE' => 'NER', 'NF' => 'NFK', 'NG' => 'NGA',
        'NI' => 'NIC', 'NL' => 'NLD', 'NO' => 'NOR', 'NP' => 'NPL',
        'NR' => 'NRU', 'NU' => 'NIU', 'NZ' => 'NZL', 'OM' => 'OMN',
        'PA' => 'PAN', 'PE' => 'PER', 'PF' => 'PYF', 'PG' => 'PNG',
        'PH' => 'PHL', 'PK' => 'PAK', 'PL' => 'POL', 'PM' => 'SPM',
        'PN' => 'PCN', 'PR' => 'PRI', 'PS' => 'PSE', 'PT' => 'PRT',
        'PW' => 'PLW', 'PY' => 'PRY', 'QA' => 'QAT', 'RE' => 'REU',
        'RO' => 'ROU', 'RS' => 'SRB', 'RU' => 'RUS', 'RW' => 'RWA',
        'SA' => 'SAU', 'SB' => 'SLB', 'SC' => 'SYC', 'SD' => 'SDN',
        'SE' => 'SWE', 'SG' => 'SGP', 'SH' => 'SHN', 'SI' => 'SVN',
        'SJ' => 'SJM', 'SK' => 'SVK', 'SL' => 'SLE', 'SM' => 'SMR',
        'SN' => 'SEN', 'SO' => 'SOM', 'SR' => 'SUR', 'SS' => 'SSD',
        'ST' => 'STP', 'SV' => 'SLV', 'SX' => 'SXM', 'SY' => 'SYR',
        'SZ' => 'SWZ', 'TC' => 'TCA', 'TD' => 'TCD', 'TF' => 'ATF',
        'TG' => 'TGO', 'TH' => 'THA', 'TJ' => 'TJK', 'TK' => 'TKL',
        'TL' => 'TLS', 'TM' => 'TKM', 'TN' => 'TUN', 'TO' => 'TON',
        'TR' => 'TUR', 'TT' => 'TTO', 'TV' => 'TUV', 'TW' => 'TWN',
        'TZ' => 'TZA', 'UA' => 'UKR', 'UG' => 'UGA', 'UM' => 'UMI',
        'US' => 'USA', 'UY' => 'URY', 'UZ' => 'UZB', 'VA' => 'VAT',
        'VC' => 'VCT', 'VE' => 'VEN', 'VG' => 'VGB', 'VI' => 'VIR',
        'VN' => 'VNM', 'VU' => 'VUT', 'WF' => 'WLF', 'WS' => 'WSM',
        'YE' => 'YEM', 'YT' => 'MYT', 'ZA' => 'ZAF', 'ZM' => 'ZMB',
        'ZW' => 'ZWE',
        // 1A2 用户分配（非正式ISO，但Cz88/MaxMind可能出现，便于兼容）
        'XK' => 'XKX',
        // 自定义状态码（_ 前缀，不与标准码冲突）
        '_ZZ'  => '_ZZZ',   // 解析失败/真正未知
        '_LA'  => '_LAN',   // 局域网/私有IP
    ];

    /**
     * 中文国家/地区名 => alpha-2 映射表
     * 覆盖 MaxMind / Cz88 经 convAreaMap 处理后的国家级名称
     *
     * 注意：中国大陆/台湾/香港/澳门的名称与 Base.php / 各驱动的 convAreaMap 输出保持一致
     * 例如 Cz88 输出 "中国–台湾" 经 convAreaMap 后变为 "中国台湾–台湾"，取首段 "中国台湾"
     *      MaxMind 输出 "台湾" 经 convAreaMap 后变为 "中国台湾"
     */
    const CN_TO_ALPHA2 = [
        // === 中国及港澳台（convAreaMap 处理后的名称）===
        '中国大陆'   => 'CN',
        '中国台湾'   => 'TW',
        '中国香港'   => 'HK',
        '中国澳门'   => 'MO',
        // 兼容未经 convAreaMap 处理的原始名称
        '中国'      => 'CN',
        '台湾'      => 'TW',
        '香港'      => 'HK',
        '澳门'      => 'MO',

        // === 亚洲 ===
        '日本'      => 'JP',
        '韩国'      => 'KR',
        '朝鲜'      => 'KP',
        '蒙古'      => 'MN',
        '越南'      => 'VN',
        '泰国'      => 'TH',
        '缅甸'      => 'MM',
        '老挝'      => 'LA',
        '柬埔寨'    => 'KH',
        '马来西亚'  => 'MY',
        '新加坡'    => 'SG',
        '印度尼西亚' => 'ID',
        '印尼'      => 'ID',
        '菲律宾'    => 'PH',
        '文莱'      => 'BN',
        '东帝汶'    => 'TL',
        '印度'      => 'IN',
        '巴基斯坦'  => 'PK',
        '孟加拉'    => 'BD',
        '孟加拉国'  => 'BD',
        '斯里兰卡'  => 'LK',
        '马尔代夫'  => 'MV',
        '尼泊尔'    => 'NP',
        '不丹'      => 'BT',
        '阿富汗'    => 'AF',
        '伊朗'      => 'IR',
        '伊拉克'    => 'IQ',
        '沙特阿拉伯' => 'SA',
        '沙特'      => 'SA',
        '阿联酋'    => 'AE',
        '阿曼'      => 'OM',
        '也门'      => 'YE',
        '约旦'      => 'JO',
        '黎巴嫩'    => 'LB',
        '叙利亚'    => 'SY',
        '以色列'    => 'IL',
        '巴勒斯坦'  => 'PS',
        '科威特'    => 'KW',
        '巴林'      => 'BH',
        '卡塔尔'    => 'QA',
        '塞浦路斯'  => 'CY',
        '土耳其'    => 'TR',
        '哈萨克斯坦' => 'KZ',
        '乌兹别克斯坦' => 'UZ',
        '土库曼斯坦' => 'TM',
        '吉尔吉斯斯坦' => 'KG',
        '塔吉克斯坦' => 'TJ',
        '格鲁吉亚'  => 'GE',
        '亚美尼亚'  => 'AM',
        '阿塞拜疆'  => 'AZ',

        // === 欧洲 ===
        '英国'      => 'GB',
        '法国'      => 'FR',
        '德国'      => 'DE',
        '意大利'    => 'IT',
        '西班牙'    => 'ES',
        '葡萄牙'    => 'PT',
        '荷兰'      => 'NL',
        '比利时'    => 'BE',
        '卢森堡'    => 'LU',
        '瑞士'      => 'CH',
        '奥地利'    => 'AT',
        '列支敦士登' => 'LI',
        '摩纳哥'    => 'MC',
        '安道尔'    => 'AD',
        '圣马力诺'  => 'SM',
        '梵蒂冈'    => 'VA',
        '马耳他'    => 'MT',
        '爱尔兰'    => 'IE',
        '冰岛'      => 'IS',
        '丹麦'      => 'DK',
        '挪威'      => 'NO',
        '瑞典'      => 'SE',
        '芬兰'      => 'FI',
        '爱沙尼亚'  => 'EE',
        '拉脱维亚'  => 'LV',
        '立陶宛'    => 'LT',
        '波兰'      => 'PL',
        '捷克'      => 'CZ',
        '斯洛伐克'  => 'SK',
        '匈牙利'    => 'HU',
        '罗马尼亚'  => 'RO',
        '保加利亚'  => 'BG',
        '希腊'      => 'GR',
        '阿尔巴尼亚' => 'AL',
        '北马其顿'  => 'MK',
        '前南马其顿' => 'MK',
        '塞尔维亚'  => 'RS',
        '克罗地亚'  => 'HR',
        '斯洛文尼亚' => 'SI',
        '波黑'      => 'BA',
        '波斯尼亚和黑塞哥维那' => 'BA',
        '黑山'      => 'ME',
        '科索沃'    => 'XK',
        '俄罗斯'    => 'RU',
        '俄罗斯联邦' => 'RU',
        '白俄罗斯'  => 'BY',
        '乌克兰'    => 'UA',
        '摩尔多瓦'  => 'MD',

        // === 非洲 ===
        '埃及'      => 'EG',
        '利比亚'    => 'LY',
        '突尼斯'    => 'TN',
        '阿尔及利亚' => 'DZ',
        '摩洛哥'    => 'MA',
        '苏丹'      => 'SD',
        '南苏丹'    => 'SS',
        '埃塞俄比亚' => 'ET',
        '厄立特里亚' => 'ER',
        '吉布提'    => 'DJ',
        '索马里'    => 'SO',
        '肯尼亚'    => 'KE',
        '乌干达'    => 'UG',
        '坦桑尼亚'  => 'TZ',
        '卢旺达'    => 'RW',
        '布隆迪'    => 'BI',
        '塞舌尔'    => 'SC',
        '乍得'      => 'TD',
        '中非'      => 'CF',
        '中非共和国' => 'CF',
        '喀麦隆'    => 'CM',
        '赤道几内亚' => 'GQ',
        '加蓬'      => 'GA',
        '刚果'      => 'CG',
        '刚果共和国' => 'CG',
        '刚果民主共和国' => 'CD',
        '扎伊尔'    => 'CD',
        '圣多美和普林西比' => 'ST',
        '毛里塔尼亚' => 'MR',
        '塞内加尔'  => 'SN',
        '冈比亚'    => 'GM',
        '马里'      => 'ML',
        '布基纳法索' => 'BF',
        '几内亚'    => 'GN',
        '几内亚比绍' => 'GW',
        '佛得角'    => 'CV',
        '塞拉利昂'  => 'SL',
        '利比里亚'  => 'LR',
        '科特迪瓦'  => 'CI',
        '象牙海岸'  => 'CI',
        '加纳'      => 'GH',
        '多哥'      => 'TG',
        '贝宁'      => 'BJ',
        '尼日尔'    => 'NE',
        '尼日利亚'  => 'NG',
        '安哥拉'    => 'AO',
        '赞比亚'    => 'ZM',
        '津巴布韦'  => 'ZW',
        '马拉维'    => 'MW',
        '莫桑比克'  => 'MZ',
        '博茨瓦纳'  => 'BW',
        '纳米比亚'  => 'NA',
        '南非'      => 'ZA',
        '莱索托'    => 'LS',
        '斯威士兰'  => 'SZ',
        '马达加斯加' => 'MG',
        '毛里求斯'  => 'MU',
        '科摩罗'    => 'KM',
        '留尼汪'    => 'RE',
        '西撒哈拉'  => 'EH',

        // === 北美洲 ===
        '美国'      => 'US',
        '加拿大'    => 'CA',
        '墨西哥'    => 'MX',
        '危地马拉'  => 'GT',
        '伯利兹'    => 'BZ',
        '萨尔瓦多'  => 'SV',
        '洪都拉斯'  => 'HN',
        '尼加拉瓜'  => 'NI',
        '哥斯达黎加' => 'CR',
        '巴拿马'    => 'PA',
        '古巴'      => 'CU',
        '牙买加'    => 'JM',
        '海地'      => 'HT',
        '多米尼加'  => 'DO',
        '多米尼加共和国' => 'DO',
        '巴哈马'    => 'BS',
        '特立尼达和多巴哥' => 'TT',
        '巴巴多斯'  => 'BB',
        '圣卢西亚'  => 'LC',
        '圣文森特和格林纳丁斯' => 'VC',
        '格林纳达'  => 'GD',
        '安提瓜和巴布达' => 'AG',
        '多米尼克'  => 'DM',
        '圣基茨和尼维斯' => 'KN',

        // === 南美洲 ===
        '巴西'      => 'BR',
        '阿根廷'    => 'AR',
        '智利'      => 'CL',
        '哥伦比亚'  => 'CO',
        '秘鲁'      => 'PE',
        '委内瑞拉'  => 'VE',
        '厄瓜多尔'  => 'EC',
        '玻利维亚'  => 'BO',
        '巴拉圭'    => 'PY',
        '乌拉圭'    => 'UY',
        '圭亚那'    => 'GY',
        '苏里南'    => 'SR',

        // === 大洋洲 ===
        '澳大利亚'  => 'AU',
        '新西兰'    => 'NZ',
        '巴布亚新几内亚' => 'PG',
        '斐济'      => 'FJ',
        '所罗门群岛' => 'SB',
        '瓦努阿图'  => 'VU',
        '萨摩亚'    => 'WS',
        '汤加'      => 'TO',
        '基里巴斯'  => 'KI',
        '密克罗尼西亚' => 'FM',
        '马绍尔群岛' => 'MH',
        '帕劳'      => 'PW',
        '瑙鲁'      => 'NR',
        '图瓦卢'    => 'TV',
        '库克群岛'  => 'CK',
        '纽埃'      => 'NU',

        // === 其他/特殊 ===
        '波多黎各'  => 'PR',
        '百慕大'    => 'BM',
        '格陵兰'    => 'GL',
        '关岛'      => 'GU',
        '新喀里多尼亚' => 'NC',
        '法属波利尼西亚' => 'PF',
        '马提尼克'  => 'MQ',
        '瓜德罗普'  => 'GP',
        '法属圭亚那' => 'GF',
        '阿鲁巴'    => 'AW',
        '荷属圣马丁' => 'SX',
        '库拉索'    => 'CW',
        '开曼群岛'  => 'KY',
        '维尔京群岛' => 'VG',
        '美属维尔京群岛' => 'VI',
        '直布罗陀'  => 'GI',
        '泽西岛'    => 'JE',
        '根西岛'    => 'GG',
        '马恩岛'    => 'IM',
        '法罗群岛'  => 'FO',
        '奥兰群岛'  => 'AX',
        '斯瓦尔巴和扬马延' => 'SJ',
        '马约特'    => 'YT',
        '法属圣马丁' => 'MF',
        '博内尔、圣尤斯特歇斯和萨巴' => 'BQ',
        '北马里亚纳群岛' => 'MP',
        '美属萨摩亚' => 'AS',
        '福克兰群岛(马尔维纳斯)' => 'FK',
        '福克兰群岛' => 'FK',
        '特克斯和凯科斯群岛' => 'TC',
        '圣巴泰勒米' => 'BL',
        '安圭拉'    => 'AI',
        '圣皮埃尔和密克隆' => 'PM',
        '圣诞岛'    => 'CX',
        '圣赫勒拿'  => 'SH',
        '英属印度洋领地' => 'IO',
        '瓦利斯和富图纳' => 'WF',
        '蒙特塞拉特' => 'MS',
        '南极洲'    => 'AQ',
        '布韦岛'    => 'BV',
        '科科斯（基林）群岛' => 'CC',
        '科科斯群岛' => 'CC',
        '南乔治亚和南桑威奇群岛' => 'GS',
        '赫德岛和麦克唐纳群岛' => 'HM',
        '诺福克岛'  => 'NF',
        '皮特凯恩群岛' => 'PN',
        '法属南部领地' => 'TF',
        '托克劳'    => 'TK',
        '美国本土外小岛屿' => 'UM',

        // 自定义状态（_ 前缀，不与标准码冲突）
        '局域网' => '_LA',   // 局域网/私有IP
    ];

    /**
     * alpha-2 => 中文国家名 映射表（反向规范映射，每个代码取一个标准中文名）
     * 用于 alpha2ToCn / alpha3ToCn，可通过 $override 参数覆盖
     *
     * 注意：CN/TW/HK/MO 使用 convAreaMap 处理后的名称（中国大陆/中国台湾/中国香港/中国澳门）
     * 业务方可通过 alpha2ToCn('CN', ['CN' => '中国']) 覆盖为其他名称
     */
    const ALPHA2_TO_CN = [
        // 中国及港澳台
        'CN' => '中国大陆',
        'TW' => '中国台湾',
        'HK' => '中国香港',
        'MO' => '中国澳门',

        // 亚洲
        'JP' => '日本', 'KR' => '韩国', 'KP' => '朝鲜', 'MN' => '蒙古',
        'VN' => '越南', 'TH' => '泰国', 'MM' => '缅甸', 'LA' => '老挝',
        'KH' => '柬埔寨', 'MY' => '马来西亚', 'SG' => '新加坡',
        'ID' => '印度尼西亚', 'PH' => '菲律宾', 'BN' => '文莱', 'TL' => '东帝汶',
        'IN' => '印度', 'PK' => '巴基斯坦', 'BD' => '孟加拉', 'LK' => '斯里兰卡',
        'MV' => '马尔代夫', 'NP' => '尼泊尔', 'BT' => '不丹', 'AF' => '阿富汗',
        'IR' => '伊朗', 'IQ' => '伊拉克', 'SA' => '沙特阿拉伯', 'AE' => '阿联酋',
        'OM' => '阿曼', 'YE' => '也门', 'JO' => '约旦', 'LB' => '黎巴嫩',
        'SY' => '叙利亚', 'IL' => '以色列', 'PS' => '巴勒斯坦', 'KW' => '科威特',
        'BH' => '巴林', 'QA' => '卡塔尔', 'CY' => '塞浦路斯', 'TR' => '土耳其',
        'KZ' => '哈萨克斯坦', 'UZ' => '乌兹别克斯坦', 'TM' => '土库曼斯坦',
        'KG' => '吉尔吉斯斯坦', 'TJ' => '塔吉克斯坦', 'GE' => '格鲁吉亚',
        'AM' => '亚美尼亚', 'AZ' => '阿塞拜疆',

        // 欧洲
        'GB' => '英国', 'FR' => '法国', 'DE' => '德国', 'IT' => '意大利',
        'ES' => '西班牙', 'PT' => '葡萄牙', 'NL' => '荷兰', 'BE' => '比利时',
        'LU' => '卢森堡', 'CH' => '瑞士', 'AT' => '奥地利', 'LI' => '列支敦士登',
        'MC' => '摩纳哥', 'AD' => '安道尔', 'SM' => '圣马力诺', 'VA' => '梵蒂冈',
        'MT' => '马耳他', 'IE' => '爱尔兰', 'IS' => '冰岛', 'DK' => '丹麦',
        'NO' => '挪威', 'SE' => '瑞典', 'FI' => '芬兰', 'EE' => '爱沙尼亚',
        'LV' => '拉脱维亚', 'LT' => '立陶宛', 'PL' => '波兰', 'CZ' => '捷克',
        'SK' => '斯洛伐克', 'HU' => '匈牙利', 'RO' => '罗马尼亚', 'BG' => '保加利亚',
        'GR' => '希腊', 'AL' => '阿尔巴尼亚', 'MK' => '北马其顿', 'RS' => '塞尔维亚',
        'HR' => '克罗地亚', 'SI' => '斯洛文尼亚', 'BA' => '波黑', 'ME' => '黑山',
        'XK' => '科索沃', 'RU' => '俄罗斯', 'BY' => '白俄罗斯', 'UA' => '乌克兰',
        'MD' => '摩尔多瓦',

        // 非洲
        'EG' => '埃及', 'LY' => '利比亚', 'TN' => '突尼斯', 'DZ' => '阿尔及利亚',
        'MA' => '摩洛哥', 'SD' => '苏丹', 'SS' => '南苏丹', 'ET' => '埃塞俄比亚',
        'ER' => '厄立特里亚', 'DJ' => '吉布提', 'SO' => '索马里', 'KE' => '肯尼亚',
        'UG' => '乌干达', 'TZ' => '坦桑尼亚', 'RW' => '卢旺达', 'BI' => '布隆迪',
        'SC' => '塞舌尔', 'TD' => '乍得', 'CF' => '中非', 'CM' => '喀麦隆',
        'GQ' => '赤道几内亚', 'GA' => '加蓬', 'CG' => '刚果', 'CD' => '刚果民主共和国',
        'ST' => '圣多美和普林西比', 'MR' => '毛里塔尼亚', 'SN' => '塞内加尔',
        'GM' => '冈比亚', 'ML' => '马里', 'BF' => '布基纳法索', 'GN' => '几内亚',
        'GW' => '几内亚比绍', 'CV' => '佛得角', 'SL' => '塞拉利昂', 'LR' => '利比里亚',
        'CI' => '科特迪瓦', 'GH' => '加纳', 'TG' => '多哥', 'BJ' => '贝宁',
        'NE' => '尼日尔', 'NG' => '尼日利亚', 'AO' => '安哥拉', 'ZM' => '赞比亚',
        'ZW' => '津巴布韦', 'MW' => '马拉维', 'MZ' => '莫桑比克', 'BW' => '博茨瓦纳',
        'NA' => '纳米比亚', 'ZA' => '南非', 'LS' => '莱索托', 'SZ' => '斯威士兰',
        'MG' => '马达加斯加', 'MU' => '毛里求斯', 'KM' => '科摩罗', 'RE' => '留尼汪',
        'EH' => '西撒哈拉',

        // 北美洲
        'US' => '美国', 'CA' => '加拿大', 'MX' => '墨西哥', 'GT' => '危地马拉',
        'BZ' => '伯利兹', 'SV' => '萨尔瓦多', 'HN' => '洪都拉斯', 'NI' => '尼加拉瓜',
        'CR' => '哥斯达黎加', 'PA' => '巴拿马', 'CU' => '古巴', 'JM' => '牙买加',
        'HT' => '海地', 'DO' => '多米尼加', 'BS' => '巴哈马',
        'TT' => '特立尼达和多巴哥', 'BB' => '巴巴多斯', 'LC' => '圣卢西亚',
        'VC' => '圣文森特和格林纳丁斯', 'GD' => '格林纳达', 'AG' => '安提瓜和巴布达',
        'DM' => '多米尼克', 'KN' => '圣基茨和尼维斯',

        // 南美洲
        'BR' => '巴西', 'AR' => '阿根廷', 'CL' => '智利', 'CO' => '哥伦比亚',
        'PE' => '秘鲁', 'VE' => '委内瑞拉', 'EC' => '厄瓜多尔', 'BO' => '玻利维亚',
        'PY' => '巴拉圭', 'UY' => '乌拉圭', 'GY' => '圭亚那', 'SR' => '苏里南',

        // 大洋洲
        'AU' => '澳大利亚', 'NZ' => '新西兰', 'PG' => '巴布亚新几内亚',
        'FJ' => '斐济', 'SB' => '所罗门群岛', 'VU' => '瓦努阿图', 'WS' => '萨摩亚',
        'TO' => '汤加', 'KI' => '基里巴斯', 'FM' => '密克罗尼西亚',
        'MH' => '马绍尔群岛', 'PW' => '帕劳', 'NR' => '瑙鲁', 'TV' => '图瓦卢',
        'CK' => '库克群岛', 'NU' => '纽埃',

        // 其他/特殊
        'PR' => '波多黎各', 'BM' => '百慕大', 'GL' => '格陵兰', 'GU' => '关岛',
        'NC' => '新喀里多尼亚', 'PF' => '法属波利尼西亚', 'MQ' => '马提尼克',
        'GP' => '瓜德罗普', 'GF' => '法属圭亚那', 'AW' => '阿鲁巴',
        'SX' => '荷属圣马丁', 'CW' => '库拉索', 'KY' => '开曼群岛',
        'VG' => '维尔京群岛', 'VI' => '美属维尔京群岛', 'GI' => '直布罗陀',
        'JE' => '泽西岛', 'GG' => '根西岛', 'IM' => '马恩岛', 'FO' => '法罗群岛',
        'AX' => '奥兰群岛', 'SJ' => '斯瓦尔巴和扬马延',
        'YT' => '马约特', 'MF' => '法属圣马丁',
        'BQ' => '博内尔、圣尤斯特歇斯和萨巴', 'MP' => '北马里亚纳群岛',
        'AS' => '美属萨摩亚', 'FK' => '福克兰群岛(马尔维纳斯)',
        'TC' => '特克斯和凯科斯群岛', 'BL' => '圣巴泰勒米', 'AI' => '安圭拉',
        'PM' => '圣皮埃尔和密克隆', 'CX' => '圣诞岛', 'SH' => '圣赫勒拿',
        'IO' => '英属印度洋领地', 'WF' => '瓦利斯和富图纳', 'MS' => '蒙特塞拉特',
        'AQ' => '南极洲', 'BV' => '布韦岛', 'CC' => '科科斯（基林）群岛',
        'GS' => '南乔治亚和南桑威奇群岛', 'HM' => '赫德岛和麦克唐纳群岛',
        'NF' => '诺福克岛', 'PN' => '皮特凯恩群岛', 'TF' => '法属南部领地',
        'TK' => '托克劳', 'UM' => '美国本土外小岛屿',

        // 自定义状态码（_ 前缀，不与标准码冲突）
        '_ZZ' => '未知',    // 解析失败/真正未知
        '_LA' => '局域网',  // 局域网/私有IP
    ];

    /**
     * alpha-2 转 alpha-3
     * @param string $alpha2 两位字母国家代码（大小写不敏感）
     * @return string|null 三位字母代码，未找到返回 null
     */
    public static function alpha2ToAlpha3($alpha2)
    {
        $alpha2 = strtoupper(trim((string)$alpha2));
        return self::ALPHA2_TO_ALPHA3[$alpha2] ?? null;
    }

    /**
     * alpha-3 转 alpha-2
     * @param string $alpha3 三位字母国家代码（大小写不敏感）
     * @return string|null 两位字母代码，未找到返回 null
     */
    public static function alpha3ToAlpha2($alpha3)
    {
        $alpha3 = strtoupper(trim((string)$alpha3));
        $map = array_flip(self::ALPHA2_TO_ALPHA3);
        return $map[$alpha3] ?? null;
    }

    /**
     * 中文国家名转 alpha-2
     * @param string $name 中文国家/地区名（经 convAreaMap 处理后的国家级名称）
     * @return string|null alpha-2 代码，未找到返回 null
     */
    public static function cnNameToAlpha2($name)
    {
        $name = trim((string)$name);
        return self::CN_TO_ALPHA2[$name] ?? null;
    }

    /**
     * 中文国家名转 alpha-3
     * @param string $name 中文国家/地区名
     * @return string|null alpha-3 代码，未找到返回 null
     */
    public static function cnNameToAlpha3($name)
    {
        $alpha2 = self::cnNameToAlpha2($name);
        if ($alpha2 === null) {
            return null;
        }
        return self::alpha2ToAlpha3($alpha2);
    }

    /**
     * alpha-2 转中文国家名
     * @param string $alpha2 两位字母国家代码（大小写不敏感）
     * @param array $override alpha-2 => 中文名 覆盖映射，会与默认 ALPHA2_TO_CN 合并（同名key以覆盖为准）
     *                        例如 ['CN' => '中国'] 可将默认的"中国大陆"覆盖为"中国"
     * @return string|null 中文名，未找到返回 null
     */
    public static function alpha2ToCn($alpha2, array $override = [])
    {
        $alpha2 = strtoupper(trim((string)$alpha2));
        if (empty($override)) {
            return self::ALPHA2_TO_CN[$alpha2] ?? null;
        }
        $map = self::ALPHA2_TO_CN;
        foreach ($override as $k => $v) {
            $map[strtoupper(trim((string)$k))] = $v;
        }
        return $map[$alpha2] ?? null;
    }

    /**
     * alpha-3 转中文国家名
     * @param string $alpha3 三位字母国家代码（大小写不敏感）
     * @param array $override alpha-3 => 中文名 覆盖映射，会与默认映射合并（同名key以覆盖为准）
     *                        例如 ['CHN' => '中国'] 可将默认的"中国大陆"覆盖为"中国"
     * @return string|null 中文名，未找到返回 null
     */
    public static function alpha3ToCn($alpha3, array $override = [])
    {
        $alpha3 = strtoupper(trim((string)$alpha3));

        // 直接两步查找：alpha-3 → alpha-2 → 中文名，无需静态缓存
        $flipped = array_flip(self::ALPHA2_TO_ALPHA3);
        $alpha2  = $flipped[$alpha3] ?? null;
        $cn      = $alpha2 !== null ? (self::ALPHA2_TO_CN[$alpha2] ?? null) : null;

        if (empty($override)) {
            return $cn;
        }
        // override 以 alpha-3 为 key，优先级高于默认值
        $normalizedKey = $alpha3; // 已 strtoupper
        foreach ($override as $k => $v) {
            if (strtoupper(trim((string)$k)) === $normalizedKey) {
                return $v;
            }
        }
        return $cn;
    }
}
