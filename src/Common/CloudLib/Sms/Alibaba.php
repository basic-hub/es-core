<?php

namespace BasicHub\EsCore\Common\CloudLib\Sms;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use Darabonba\OpenApi\Models\Config;

/**
 * composer require alibabacloud/dysmsapi-20170525
 */
class Alibaba extends Base
{
    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    protected $endpoint = 'dysmsapi.aliyuncs.com';

    protected $signName = '';

    protected $templateCode = '';

    protected $phoneNumbers = '';

    protected $templateParam = '';

    protected function getTemplateId()
    {
        return $this->getTplId($this->templateCode);
    }

    /**
     * @document https://help.aliyun.com/zh/sms/developer-reference/api-error-codes?spm=a2c4g.11186623.help-menu-44282.d_5_6.3f4258d0OBDUKp
     * @param $to
     * @param array $params
     * @return bool
     */
    public function send($to = [], array $params = [])
    {
        if ($this->isdebug) {
            trace(__METHOD__ . ', to=' . var_export($to, true) . ', params=' . var_export($params, true));
            return true;
        }

        $this->phoneNumbers = implode(',', is_string($to) ? [$to] : $to);
        $this->templateParam = json_encode($params);

        $log = repeat_array_keys(get_object_vars($this), ['accessKeyId', 'accessKeySecret'], 5);
        $endFn = http_tracker('SDK:SMS', [
            'url' => '__ALI_SMS__',
            'POST' => $log,
            'method' => 'POST',
        ], $this->parentId);

        try {
            $Runtime = new RuntimeOptions();
            $Request = new SendSmsRequest([
                'phoneNumbers' => $this->phoneNumbers,
                'signName' => $this->signName,
                'templateCode' => $this->getTemplateId(),
                'templateParam' => $this->templateParam
            ]);
            $Config = new Config([
                "accessKeyId" => $this->accessKeyId,
                "accessKeySecret" => $this->accessKeySecret
            ]);
            // 访问的域名
            $Config->endpoint = $this->endpoint;
            $Client = new Dysmsapi($Config);

            $resp = $Client->sendSmsWithOptions($Request, $Runtime);
            $arr = $resp->toMap();
            $endFn($arr, 200);

            $isSuccess = $resp->body->code === 'OK';

            if ( ! $isSuccess) {
                notice('阿里云短信发送失败: ' . $resp->body->message);
            }
            return $isSuccess;
        } catch (\Exception $error) {
            if ( ! ($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }

            is_callable($endFn) && $endFn($error->__toString(), $error->getCode());
            $msg = '阿里云短信发送失败: ' . $error->__toString() . ", 号码: {$this->phoneNumbers}";
            trace($msg, 'error');

            if (!in_array($error->getCode(), [
                'isv.MOBILE_NUMBER_ILLEGAL',
            ])) {
                notice($msg);
            }

            return false;
        }
    }

    /**
     * 阿里云支持的国家（或地区）电话代码
     * @document https://www.alibabacloud.com/help/zh/sms/product-overview/dialing-codes
     * 为减少Config内存占用，改为函数式调用
     * @return array[]
     */
    public static function codeList()
    {
        return [
            [
                'en' => 'China',
                'cn' => '中国大陆',
                'code' => '86',
                'alpha2' => 'CN'
            ],
            [
                'en' => 'United States of America',
                'cn' => '美利坚合众国',
                'code' => '1',
                'alpha2' => 'US'
            ],
            [
                'en' => 'Canada',
                'cn' => '加拿大',
                'code' => '1',
                'alpha2' => 'CA'
            ],
            [
                'en' => 'Russian Federation',
                'cn' => '俄罗斯联邦',
                'code' => '7',
                'alpha2' => 'RU'
            ],
            [
                'en' => 'Egypt',
                'cn' => '埃及',
                'code' => '20',
                'alpha2' => 'EG'
            ],
            [
                'en' => 'South Africa',
                'cn' => '南非',
                'code' => '27',
                'alpha2' => 'ZA'
            ],
            [
                'en' => 'Greece',
                'cn' => '希腊',
                'code' => '30',
                'alpha2' => 'GR'
            ],
            [
                'en' => 'Netherlands (Kingdom of the)',
                'cn' => '荷兰王国',
                'code' => '31',
                'alpha2' => 'NL'
            ],
            [
                'en' => 'Belgium',
                'cn' => '比利时',
                'code' => '32',
                'alpha2' => 'BE'
            ],
            [
                'en' => 'France',
                'cn' => '法国',
                'code' => '33',
                'alpha2' => 'FR'
            ],
            [
                'en' => 'Spain',
                'cn' => '西班牙',
                'code' => '34',
                'alpha2' => 'ES'
            ],
            [
                'en' => 'Hungary',
                'cn' => '匈牙利',
                'code' => '36',
                'alpha2' => 'HU'
            ],
            [
                'en' => 'Italy',
                'cn' => '意大利',
                'code' => '39',
                'alpha2' => 'IT'
            ],
            [
                'en' => 'Romania',
                'cn' => '罗马尼亚',
                'code' => '40',
                'alpha2' => 'RO'
            ],
            [
                'en' => 'Switzerland',
                'cn' => '瑞士',
                'code' => '41',
                'alpha2' => 'CH'
            ],
            [
                'en' => 'Austria',
                'cn' => '奥地利',
                'code' => '43',
                'alpha2' => 'AT'
            ],
            [
                'en' => 'United Kingdom of Great Britain and Northern Ireland',
                'cn' => '大不列颠及北爱尔兰联合王国',
                'code' => '44',
                'alpha2' => 'GB'
            ],
            [
                'en' => 'Denmark',
                'cn' => '丹麦',
                'code' => '45',
                'alpha2' => 'DK'
            ],
            [
                'en' => 'Sweden',
                'cn' => '瑞典',
                'code' => '46',
                'alpha2' => 'SE'
            ],
            [
                'en' => 'Norway',
                'cn' => '挪威',
                'code' => '47',
                'alpha2' => 'NO'
            ],
            [
                'en' => 'Poland',
                'cn' => '波兰',
                'code' => '48',
                'alpha2' => 'PL'
            ],
            [
                'en' => 'Germany',
                'cn' => '德国',
                'code' => '49',
                'alpha2' => 'DE'
            ],
            [
                'en' => 'Peru',
                'cn' => '秘鲁',
                'code' => '51',
                'alpha2' => 'PE'
            ],
            [
                'en' => 'Mexico',
                'cn' => '墨西哥',
                'code' => '52',
                'alpha2' => 'MX'
            ],
            [
                'en' => 'Cuba',
                'cn' => '古巴',
                'code' => '53',
                'alpha2' => 'CU'
            ],
            [
                'en' => 'Argentina',
                'cn' => '阿根廷',
                'code' => '54',
                'alpha2' => 'AR'
            ],
            [
                'en' => 'Brazil',
                'cn' => '巴西',
                'code' => '55',
                'alpha2' => 'BR'
            ],
            [
                'en' => 'Chile',
                'cn' => '智利',
                'code' => '56',
                'alpha2' => 'CL'
            ],
            [
                'en' => 'Colombia',
                'cn' => '哥伦比亚',
                'code' => '57',
                'alpha2' => 'CO'
            ],
            [
                'en' => 'Venezuela (Bolivarian Republic of)',
                'cn' => '委内瑞拉玻利瓦尔共和国',
                'code' => '58',
                'alpha2' => 'VE'
            ],
            [
                'en' => 'Malaysia',
                'cn' => '马来西亚',
                'code' => '60',
                'alpha2' => 'MY'
            ],
            [
                'en' => 'Australia',
                'cn' => '澳大利亚',
                'code' => '61',
                'alpha2' => 'AU'
            ],
            [
                'en' => 'Indonesia',
                'cn' => '印度尼西亚',
                'code' => '62',
                'alpha2' => 'ID'
            ],
            [
                'en' => 'Philippines',
                'cn' => '菲律宾',
                'code' => '63',
                'alpha2' => 'PH'
            ],
            [
                'en' => 'New Zealand',
                'cn' => '新西兰',
                'code' => '64',
                'alpha2' => 'NZ'
            ],
            [
                'en' => 'Pitcairn Islands',
                'cn' => '皮特凯恩群岛',
                'code' => '64',
                'alpha2' => 'PN'
            ],
            [
                'en' => 'Singapore',
                'cn' => '新加坡',
                'code' => '65',
                'alpha2' => 'SG'
            ],
            [
                'en' => 'Thailand',
                'cn' => '泰国',
                'code' => '66',
                'alpha2' => 'TH'
            ],
            [
                'en' => 'Japan',
                'cn' => '日本',
                'code' => '81',
                'alpha2' => 'JP'
            ],
            [
                'en' => 'Republic of Korea',
                'cn' => '大韩民国',
                'code' => '82',
                'alpha2' => 'KR'
            ],
            [
                'en' => 'Viet Nam',
                'cn' => '越南',
                'code' => '84',
                'alpha2' => 'VN'
            ],
            [
                'en' => 'Turkiye',
                'cn' => '土耳其',
                'code' => '90',
                'alpha2' => 'TR'
            ],
            [
                'en' => 'India',
                'cn' => '印度',
                'code' => '91',
                'alpha2' => 'IN'
            ],
            [
                'en' => 'Pakistan',
                'cn' => '巴基斯坦',
                'code' => '92',
                'alpha2' => 'PK'
            ],
            [
                'en' => 'Afghanistan',
                'cn' => '阿富汗',
                'code' => '93',
                'alpha2' => 'AF'
            ],
            [
                'en' => 'Sri Lanka',
                'cn' => '斯里兰卡',
                'code' => '94',
                'alpha2' => 'LK'
            ],
            [
                'en' => 'Myanmar',
                'cn' => '缅甸',
                'code' => '95',
                'alpha2' => 'MM'
            ],
            [
                'en' => 'Iran (Islamic Republic of)',
                'cn' => '伊朗伊斯兰共和国',
                'code' => '98',
                'alpha2' => 'IR'
            ],
            [
                'en' => 'South Sudan',
                'cn' => '南苏丹',
                'code' => '211',
                'alpha2' => 'SS'
            ],
            [
                'en' => 'Morocco',
                'cn' => '摩洛哥',
                'code' => '212',
                'alpha2' => 'MA'
            ],
            [
                'en' => 'Algeria',
                'cn' => '阿尔及利亚',
                'code' => '213',
                'alpha2' => 'DZ'
            ],
            [
                'en' => 'Tunisia',
                'cn' => '突尼斯',
                'code' => '216',
                'alpha2' => 'TN'
            ],
            [
                'en' => 'Libya',
                'cn' => '利比亚',
                'code' => '218',
                'alpha2' => 'LY'
            ],
            [
                'en' => 'Gambia',
                'cn' => '冈比亚',
                'code' => '220',
                'alpha2' => 'GM'
            ],
            [
                'en' => 'Senegal',
                'cn' => '塞内加尔',
                'code' => '221',
                'alpha2' => 'SN'
            ],
            [
                'en' => 'Mauritania',
                'cn' => '毛里塔尼亚',
                'code' => '222',
                'alpha2' => 'MR'
            ],
            [
                'en' => 'Mali',
                'cn' => '马里',
                'code' => '223',
                'alpha2' => 'ML'
            ],
            [
                'en' => 'Guinea',
                'cn' => '几内亚',
                'code' => '224',
                'alpha2' => 'GN'
            ],
            [
                'en' => 'Cote d’Ivoire',
                'cn' => '科特迪瓦',
                'code' => '225',
                'alpha2' => 'CI'
            ],
            [
                'en' => 'Burkina Faso',
                'cn' => '布基纳法索',
                'code' => '226',
                'alpha2' => 'BF'
            ],
            [
                'en' => 'Niger',
                'cn' => '尼日尔',
                'code' => '227',
                'alpha2' => 'NE'
            ],
            [
                'en' => 'Togo',
                'cn' => '多哥',
                'code' => '228',
                'alpha2' => 'TG'
            ],
            [
                'en' => 'Benin',
                'cn' => '贝宁',
                'code' => '229',
                'alpha2' => 'BJ'
            ],
            [
                'en' => 'Mauritius',
                'cn' => '毛里求斯',
                'code' => '230',
                'alpha2' => 'MU'
            ],
            [
                'en' => 'Liberia',
                'cn' => '利比里亚',
                'code' => '231',
                'alpha2' => 'LR'
            ],
            [
                'en' => 'Sierra Leone',
                'cn' => '塞拉利昂',
                'code' => '232',
                'alpha2' => 'SL'
            ],
            [
                'en' => 'Ghana',
                'cn' => '加纳',
                'code' => '233',
                'alpha2' => 'GH'
            ],
            [
                'en' => 'Nigeria',
                'cn' => '尼日利亚',
                'code' => '234',
                'alpha2' => 'NG'
            ],
            [
                'en' => 'Chad',
                'cn' => '乍得',
                'code' => '235',
                'alpha2' => 'TD'
            ],
            [
                'en' => 'Central African Republic',
                'cn' => '中非共和国',
                'code' => '236',
                'alpha2' => 'CF'
            ],
            [
                'en' => 'Cameroon',
                'cn' => '喀麦隆',
                'code' => '237',
                'alpha2' => 'CM'
            ],
            [
                'en' => 'Cabo Verde',
                'cn' => '佛得角',
                'code' => '238',
                'alpha2' => 'CV'
            ],
            [
                'en' => 'Sao Tome and Principe',
                'cn' => '圣多美和普林西比',
                'code' => '239',
                'alpha2' => 'ST'
            ],
            [
                'en' => 'Equatorial Guinea',
                'cn' => '赤道几内亚',
                'code' => '240',
                'alpha2' => 'GQ'
            ],
            [
                'en' => 'Gabon',
                'cn' => '加蓬',
                'code' => '241',
                'alpha2' => 'GA'
            ],
            [
                'en' => 'Congo',
                'cn' => '刚果',
                'code' => '242',
                'alpha2' => 'CG'
            ],
            [
                'en' => 'Democratic Republic of the Congo',
                'cn' => '刚果民主共和国',
                'code' => '243',
                'alpha2' => 'CD'
            ],
            [
                'en' => 'Angola',
                'cn' => '安哥拉',
                'code' => '244',
                'alpha2' => 'AO'
            ],
            [
                'en' => 'Guinea-Bissau',
                'cn' => '几内亚比绍',
                'code' => '245',
                'alpha2' => 'GW'
            ],
            [
                'en' => 'Ascension Island',
                'cn' => '阿森松岛',
                'code' => '247',
                'alpha2' => 'AC'
            ],
            [
                'en' => 'Seychelles',
                'cn' => '塞舌尔',
                'code' => '248',
                'alpha2' => 'SC'
            ],
            [
                'en' => 'Sudan',
                'cn' => '苏丹',
                'code' => '249',
                'alpha2' => 'SD'
            ],
            [
                'en' => 'Rwanda',
                'cn' => '卢旺达',
                'code' => '250',
                'alpha2' => 'RW'
            ],
            [
                'en' => 'Ethiopia',
                'cn' => '埃塞俄比亚',
                'code' => '251',
                'alpha2' => 'ET'
            ],
            [
                'en' => 'Somalia',
                'cn' => '索马里',
                'code' => '252',
                'alpha2' => 'SO'
            ],
            [
                'en' => 'Djibouti',
                'cn' => '吉布提',
                'code' => '253',
                'alpha2' => 'DJ'
            ],
            [
                'en' => 'Kenya',
                'cn' => '肯尼亚',
                'code' => '254',
                'alpha2' => 'KE'
            ],
            [
                'en' => 'United Republic of Tanzania',
                'cn' => '坦桑尼亚联合共和国',
                'code' => '255',
                'alpha2' => 'TZ'
            ],
            [
                'en' => 'Uganda',
                'cn' => '乌干达',
                'code' => '256',
                'alpha2' => 'UG'
            ],
            [
                'en' => 'Burundi',
                'cn' => '布隆迪',
                'code' => '257',
                'alpha2' => 'BI'
            ],
            [
                'en' => 'Mozambique',
                'cn' => '莫桑比克',
                'code' => '258',
                'alpha2' => 'MZ'
            ],
            [
                'en' => 'Zambia',
                'cn' => '赞比亚',
                'code' => '260',
                'alpha2' => 'ZM'
            ],
            [
                'en' => 'Madagascar',
                'cn' => '马达加斯加',
                'code' => '261',
                'alpha2' => 'MG'
            ],
            [
                'en' => 'Reunion',
                'cn' => '留尼汪',
                'code' => '262',
                'alpha2' => 'RE'
            ],
            [
                'en' => 'Zimbabwe',
                'cn' => '津巴布韦',
                'code' => '263',
                'alpha2' => 'ZW'
            ],
            [
                'en' => 'Namibia',
                'cn' => '纳米比亚',
                'code' => '264',
                'alpha2' => 'NA'
            ],
            [
                'en' => 'Malawi',
                'cn' => '马拉维',
                'code' => '265',
                'alpha2' => 'MW'
            ],
            [
                'en' => 'Lesotho',
                'cn' => '莱索托',
                'code' => '266',
                'alpha2' => 'LS'
            ],
            [
                'en' => 'Botswana',
                'cn' => '博茨瓦纳',
                'code' => '267',
                'alpha2' => 'BW'
            ],
            [
                'en' => 'Eswatini',
                'cn' => '斯威士兰',
                'code' => '268',
                'alpha2' => 'SZ'
            ],
            [
                'en' => 'Comoros',
                'cn' => '科摩罗',
                'code' => '269',
                'alpha2' => 'KM'
            ],
            [
                'en' => 'Eritrea',
                'cn' => '厄立特里亚',
                'code' => '291',
                'alpha2' => 'ER'
            ],
            [
                'en' => 'Aruba',
                'cn' => '阿鲁巴',
                'code' => '297',
                'alpha2' => 'AW'
            ],
            [
                'en' => 'Faroe Islands',
                'cn' => '法罗群岛',
                'code' => '298',
                'alpha2' => 'FO'
            ],
            [
                'en' => 'Greenland',
                'cn' => '格陵兰',
                'code' => '299',
                'alpha2' => 'GL'
            ],
            [
                'en' => 'Gibraltar',
                'cn' => '直布罗陀',
                'code' => '350',
                'alpha2' => 'GI'
            ],
            [
                'en' => 'Portugal',
                'cn' => '葡萄牙',
                'code' => '351',
                'alpha2' => 'PT'
            ],
            [
                'en' => 'Luxembourg',
                'cn' => '卢森堡',
                'code' => '352',
                'alpha2' => 'LU'
            ],
            [
                'en' => 'Ireland',
                'cn' => '爱尔兰',
                'code' => '353',
                'alpha2' => 'IE'
            ],
            [
                'en' => 'Iceland',
                'cn' => '冰岛',
                'code' => '354',
                'alpha2' => 'IS'
            ],
            [
                'en' => 'Albania',
                'cn' => '阿尔巴尼亚',
                'code' => '355',
                'alpha2' => 'AL'
            ],
            [
                'en' => 'Malta',
                'cn' => '马耳他',
                'code' => '356',
                'alpha2' => 'MT'
            ],
            [
                'en' => 'Cyprus',
                'cn' => '塞浦路斯',
                'code' => '357',
                'alpha2' => 'CY'
            ],
            [
                'en' => 'Finland',
                'cn' => '芬兰',
                'code' => '358',
                'alpha2' => 'FI'
            ],
            [
                'en' => 'Bulgaria',
                'cn' => '保加利亚',
                'code' => '359',
                'alpha2' => 'BG'
            ],
            [
                'en' => 'Lithuania',
                'cn' => '立陶宛',
                'code' => '370',
                'alpha2' => 'LT'
            ],
            [
                'en' => 'Latvia',
                'cn' => '拉脱维亚',
                'code' => '371',
                'alpha2' => 'LV'
            ],
            [
                'en' => 'Estonia',
                'cn' => '爱沙尼亚',
                'code' => '372',
                'alpha2' => 'EE'
            ],
            [
                'en' => 'Republic of Moldova',
                'cn' => '摩尔多瓦共和国',
                'code' => '373',
                'alpha2' => 'MD'
            ],
            [
                'en' => 'Armenia',
                'cn' => '亚美尼亚',
                'code' => '374',
                'alpha2' => 'AM'
            ],
            [
                'en' => 'Belarus',
                'cn' => '白俄罗斯',
                'code' => '375',
                'alpha2' => 'BY'
            ],
            [
                'en' => 'Andorra',
                'cn' => '安道尔',
                'code' => '376',
                'alpha2' => 'AD'
            ],
            [
                'en' => 'Monaco',
                'cn' => '摩纳哥',
                'code' => '377',
                'alpha2' => 'MC'
            ],
            [
                'en' => 'San Marino',
                'cn' => '圣马力诺',
                'code' => '378',
                'alpha2' => 'SM'
            ],
            [
                'en' => 'Ukraine',
                'cn' => '乌克兰',
                'code' => '380',
                'alpha2' => 'UA'
            ],
            [
                'en' => 'Serbia',
                'cn' => '塞尔维亚',
                'code' => '381',
                'alpha2' => 'RS'
            ],
            [
                'en' => 'Montenegro',
                'cn' => '黑山',
                'code' => '382',
                'alpha2' => 'ME'
            ],
            [
                'en' => 'Croatia',
                'cn' => '克罗地亚',
                'code' => '385',
                'alpha2' => 'HR'
            ],
            [
                'en' => 'Slovenia',
                'cn' => '斯洛文尼亚',
                'code' => '386',
                'alpha2' => 'SI'
            ],
            [
                'en' => 'Bosnia and Herzegovina',
                'cn' => '波斯尼亚和黑塞哥维那',
                'code' => '387',
                'alpha2' => 'BA'
            ],
            [
                'en' => 'North Macedonia',
                'cn' => '北马其顿',
                'code' => '389',
                'alpha2' => 'MK'
            ],
            [
                'en' => 'Czechia',
                'cn' => '捷克',
                'code' => '420',
                'alpha2' => 'CZ'
            ],
            [
                'en' => 'Slovakia',
                'cn' => '斯洛伐克',
                'code' => '421',
                'alpha2' => 'SK'
            ],
            [
                'en' => 'Liechtenstein',
                'cn' => '列支敦士登',
                'code' => '423',
                'alpha2' => 'LI'
            ],
            [
                'en' => 'Falkland Islands (Malvinas)',
                'cn' => '福克兰群岛（马尔维纳斯）',
                'code' => '500',
                'alpha2' => 'FK'
            ],
            [
                'en' => 'Belize',
                'cn' => '伯利兹',
                'code' => '501',
                'alpha2' => 'BZ'
            ],
            [
                'en' => 'Guatemala',
                'cn' => '危地马拉',
                'code' => '502',
                'alpha2' => 'GT'
            ],
            [
                'en' => 'El Salvador',
                'cn' => '萨尔瓦多',
                'code' => '503',
                'alpha2' => 'SV'
            ],
            [
                'en' => 'Honduras',
                'cn' => '洪都拉斯',
                'code' => '504',
                'alpha2' => 'HN'
            ],
            [
                'en' => 'Nicaragua',
                'cn' => '尼加拉瓜',
                'code' => '505',
                'alpha2' => 'NI'
            ],
            [
                'en' => 'Costa Rica',
                'cn' => '哥斯达黎加',
                'code' => '506',
                'alpha2' => 'CR'
            ],
            [
                'en' => 'Panama',
                'cn' => '巴拿马',
                'code' => '507',
                'alpha2' => 'PA'
            ],
            [
                'en' => 'Saint Pierre and Miquelon',
                'cn' => '圣皮埃尔和密克隆',
                'code' => '508',
                'alpha2' => 'PM'
            ],
            [
                'en' => 'Haiti',
                'cn' => '海地',
                'code' => '509',
                'alpha2' => 'HT'
            ],
            [
                'en' => 'Guadeloupe',
                'cn' => '瓜德罗普',
                'code' => '590',
                'alpha2' => 'GP'
            ],
            [
                'en' => 'Bolivia (Plurinational State of)',
                'cn' => '多民族玻利维亚国',
                'code' => '591',
                'alpha2' => 'BO'
            ],
            [
                'en' => 'Guyana',
                'cn' => '圭亚那',
                'code' => '592',
                'alpha2' => 'GY'
            ],
            [
                'en' => 'Ecuador',
                'cn' => '厄瓜多尔',
                'code' => '593',
                'alpha2' => 'EC'
            ],
            [
                'en' => 'French Guiana',
                'cn' => '法属西印度群岛',
                'code' => '594',
                'alpha2' => 'FG'
            ],
            [
                'en' => 'Paraguay',
                'cn' => '巴拉圭',
                'code' => '595',
                'alpha2' => 'PY'
            ],
            [
                'en' => 'Martinique',
                'cn' => '马提尼克',
                'code' => '596',
                'alpha2' => 'MQ'
            ],
            [
                'en' => 'Suriname',
                'cn' => '苏里南',
                'code' => '597',
                'alpha2' => 'SR'
            ],
            [
                'en' => 'Uruguay',
                'cn' => '乌拉圭',
                'code' => '598',
                'alpha2' => 'UY'
            ],
            [
                'en' => 'Curacao',
                'cn' => '库拉索',
                'code' => '599',
                'alpha2' => 'CW'
            ],
            [
                'en' => 'Timor-Leste',
                'cn' => '东帝汶',
                'code' => '670',
                'alpha2' => 'TP'
            ],
            [
                'en' => 'Norfolk Island',
                'cn' => '诺福克岛',
                'code' => '672',
                'alpha2' => 'NF'
            ],
            [
                'en' => 'Brunei Darussalam',
                'cn' => '文莱达鲁萨兰国',
                'code' => '673',
                'alpha2' => 'BN'
            ],
            [
                'en' => 'Nauru',
                'cn' => '瑙鲁',
                'code' => '674',
                'alpha2' => 'NR'
            ],
            [
                'en' => 'Papua New Guinea',
                'cn' => '巴布亚新几内亚',
                'code' => '675',
                'alpha2' => 'PG'
            ],
            [
                'en' => 'Tonga',
                'cn' => '汤加',
                'code' => '676',
                'alpha2' => 'TO'
            ],
            [
                'en' => 'Solomon Islands',
                'cn' => '所罗门群岛',
                'code' => '677',
                'alpha2' => 'SB'
            ],
            [
                'en' => 'Vanuatu',
                'cn' => '瓦努阿图',
                'code' => '678',
                'alpha2' => 'VU'
            ],
            [
                'en' => 'Fiji',
                'cn' => '斐济',
                'code' => '679',
                'alpha2' => 'FJ'
            ],
            [
                'en' => 'Palau',
                'cn' => '帕劳',
                'code' => '680',
                'alpha2' => 'PW'
            ],
            [
                'en' => 'Wallis and Futuna Islands',
                'cn' => '瓦利斯群岛和富图纳群岛',
                'code' => '681',
                'alpha2' => 'WF'
            ],
            [
                'en' => 'Cook Islands',
                'cn' => '库克群岛',
                'code' => '682',
                'alpha2' => 'CK'
            ],
            [
                'en' => 'Niue',
                'cn' => '纽埃',
                'code' => '683',
                'alpha2' => 'NU'
            ],
            [
                'en' => 'Samoa',
                'cn' => '萨摩亚',
                'code' => '685',
                'alpha2' => 'WS'
            ],
            [
                'en' => 'Kiribati',
                'cn' => '基里巴斯',
                'code' => '686',
                'alpha2' => 'KI'
            ],
            [
                'en' => 'New Caledonia',
                'cn' => '新喀里多尼亚',
                'code' => '687',
                'alpha2' => 'NC'
            ],
            [
                'en' => 'Tuvalu',
                'cn' => '图瓦卢',
                'code' => '688',
                'alpha2' => 'TV'
            ],
            [
                'en' => 'French Polynesia',
                'cn' => '法属波利尼西亚',
                'code' => '689',
                'alpha2' => 'PF'
            ],
            [
                'en' => 'Tokelau',
                'cn' => '托克劳',
                'code' => '690',
                'alpha2' => 'TK'
            ],
            [
                'en' => 'Micronesia (Federated States of)',
                'cn' => '密克罗尼西亚联邦',
                'code' => '691',
                'alpha2' => 'FM'
            ],
            [
                'en' => 'Marshall Islands',
                'cn' => '马绍尔群岛',
                'code' => '692',
                'alpha2' => 'MH'
            ],
            [
                'en' => 'Democratic People\'s Republic of Korea',
                'cn' => '朝鲜民主主义人民共和国',
                'code' => '850',
                'alpha2' => 'KP'
            ],
            [
                'en' => 'China, Hong Kong Special Administrative Region',
                'cn' => '中国香港',
                'code' => '852',
                'alpha2' => 'HK'
            ],
            [
                'en' => 'China, Macao Special Administrative Region',
                'cn' => '中国澳门',
                'code' => '853',
                'alpha2' => 'MO'
            ],
            [
                'en' => 'Cambodia',
                'cn' => '柬埔寨',
                'code' => '855',
                'alpha2' => 'KH'
            ],
            [
                'en' => 'Lao People\'s Democratic Republic',
                'cn' => '老挝人民民主共和国',
                'code' => '856',
                'alpha2' => 'LA'
            ],
            [
                'en' => 'Bangladesh',
                'cn' => '孟加拉国',
                'code' => '880',
                'alpha2' => 'BD'
            ],
            [
                'en' => 'Taiwan Province of China',
                'cn' => '中国台湾',
                'code' => '886',
                'alpha2' => 'TW'
            ],
            [
                'en' => 'Maldives',
                'cn' => '马尔代夫',
                'code' => '960',
                'alpha2' => 'MV'
            ],
            [
                'en' => 'Lebanon',
                'cn' => '黎巴嫩',
                'code' => '961',
                'alpha2' => 'LB'
            ],
            [
                'en' => 'Jordan',
                'cn' => '约旦',
                'code' => '962',
                'alpha2' => 'JO'
            ],
            [
                'en' => 'Syrian Arab Republic',
                'cn' => '阿拉伯叙利亚共和国',
                'code' => '963',
                'alpha2' => 'SY'
            ],
            [
                'en' => 'Iraq',
                'cn' => '伊拉克',
                'code' => '964',
                'alpha2' => 'IQ'
            ],
            [
                'en' => 'Kuwait',
                'cn' => '科威特',
                'code' => '965',
                'alpha2' => 'KW'
            ],
            [
                'en' => 'Saudi Arabia',
                'cn' => '沙特阿拉伯',
                'code' => '966',
                'alpha2' => 'SA'
            ],
            [
                'en' => 'Yemen',
                'cn' => '也门',
                'code' => '967',
                'alpha2' => 'YE'
            ],
            [
                'en' => 'Oman',
                'cn' => '阿曼',
                'code' => '968',
                'alpha2' => 'OM'
            ],
            [
                'en' => 'State of Palestine',
                'cn' => '巴勒斯坦国',
                'code' => '970',
                'alpha2' => 'PS'
            ],
            [
                'en' => 'United Arab Emirates',
                'cn' => '阿拉伯联合酋长国',
                'code' => '971',
                'alpha2' => 'AE'
            ],
            [
                'en' => 'Israel',
                'cn' => '以色列',
                'code' => '972',
                'alpha2' => 'IL'
            ],
            [
                'en' => 'Bahrain',
                'cn' => '巴林',
                'code' => '973',
                'alpha2' => 'BH'
            ],
            [
                'en' => 'Qatar',
                'cn' => '卡塔尔',
                'code' => '974',
                'alpha2' => 'QA'
            ],
            [
                'en' => 'Bhutan',
                'cn' => '不丹',
                'code' => '975',
                'alpha2' => 'BT'
            ],
            [
                'en' => 'Mongolia',
                'cn' => '蒙古',
                'code' => '976',
                'alpha2' => 'MN'
            ],
            [
                'en' => 'Nepal',
                'cn' => '尼泊尔',
                'code' => '977',
                'alpha2' => 'NP'
            ],
            [
                'en' => 'Tajikistan',
                'cn' => '塔吉克斯坦',
                'code' => '992',
                'alpha2' => 'TJ'
            ],
            [
                'en' => 'Turkmenistan',
                'cn' => '土库曼斯坦',
                'code' => '993',
                'alpha2' => 'TM'
            ],
            [
                'en' => 'Azerbaijan',
                'cn' => '阿塞拜疆',
                'code' => '994',
                'alpha2' => 'AZ'
            ],
            [
                'en' => 'Georgia',
                'cn' => '格鲁吉亚',
                'code' => '995',
                'alpha2' => 'GE'
            ],
            [
                'en' => 'Kyrgyzstan',
                'cn' => '吉尔吉斯斯坦',
                'code' => '996',
                'alpha2' => 'KG'
            ],
            [
                'en' => 'Uzbekistan',
                'cn' => '乌兹别克斯坦',
                'code' => '998',
                'alpha2' => 'UZ'
            ],
            [
                'en' => 'Bahamas',
                'cn' => '巴哈马',
                'code' => '1242',
                'alpha2' => 'BS'
            ],
            [
                'en' => 'Barbados',
                'cn' => '巴巴多斯',
                'code' => '1246',
                'alpha2' => 'BB'
            ],
            [
                'en' => 'Anguilla',
                'cn' => '安圭拉',
                'code' => '1264',
                'alpha2' => 'AI'
            ],
            [
                'en' => 'Antigua and Barbuda',
                'cn' => '安提瓜和巴布达',
                'code' => '1268',
                'alpha2' => 'AG'
            ],
            [
                'en' => 'British Virgin Islands',
                'cn' => '英属维尔京群岛',
                'code' => '1284',
                'alpha2' => 'VG'
            ],
            [
                'en' => 'United States Virgin Islands',
                'cn' => '美属维尔京群岛',
                'code' => '1340',
                'alpha2' => 'VI'
            ],
            [
                'en' => 'Cayman Islands',
                'cn' => '开曼群岛',
                'code' => '1345',
                'alpha2' => 'KY'
            ],
            [
                'en' => 'Bermuda',
                'cn' => '百慕大',
                'code' => '1441',
                'alpha2' => 'BM'
            ],
            [
                'en' => 'Grenada',
                'cn' => '格林纳达',
                'code' => '1473',
                'alpha2' => 'GD'
            ],
            [
                'en' => 'Turks and Caicos Islands',
                'cn' => '特克斯和凯科斯群岛',
                'code' => '1649',
                'alpha2' => 'TC'
            ],
            [
                'en' => 'Montserrat',
                'cn' => '蒙特塞拉特',
                'code' => '1664',
                'alpha2' => 'MS'
            ],
            [
                'en' => 'Northern Mariana Islands',
                'cn' => '北马里亚纳群岛',
                'code' => '1670',
                'alpha2' => 'MP'
            ],
            [
                'en' => 'Guam',
                'cn' => '关岛',
                'code' => '1671',
                'alpha2' => 'GU'
            ],
            [
                'en' => 'American Samoa',
                'cn' => '美属萨摩亚',
                'code' => '1684',
                'alpha2' => 'AS'
            ],
            [
                'en' => 'Sint Maarten (Dutch part)',
                'cn' => '圣马丁（荷属）',
                'code' => '1721',
                'alpha2' => 'SX'
            ],
            [
                'en' => 'Saint Lucia',
                'cn' => '圣卢西亚',
                'code' => '1758',
                'alpha2' => 'LC'
            ],
            [
                'en' => 'Dominica',
                'cn' => '多米尼克',
                'code' => '1767',
                'alpha2' => 'DM'
            ],
            [
                'en' => 'Saint Vincent and the Grenadines',
                'cn' => '圣文森特和格林纳丁斯',
                'code' => '1784',
                'alpha2' => 'VC'
            ],
            [
                'en' => 'Trinidad and Tobago',
                'cn' => '特立尼达和多巴哥',
                'code' => '1868',
                'alpha2' => 'TT'
            ],
            [
                'en' => 'Saint Kitts and Nevis',
                'cn' => '圣基茨和尼维斯',
                'code' => '1869',
                'alpha2' => 'KN'
            ],
            [
                'en' => 'Kazakhstan',
                'cn' => '哈萨克斯坦',
                'code' => '7',
                'alpha2' => 'KZ'
            ],
            [
                'en' => 'Saint Helena',
                'cn' => '圣赫勒拿',
                'code' => '247',
                'alpha2' => 'SH'
            ],
            [
                'en' => 'Jamaica',
                'cn' => '牙买加',
                'code' => '1658',
                'alpha2' => 'JM'
            ],
            [
                'en' => 'Puerto Rico',
                'cn' => '波多黎各',
                'code' => '1787',
                'alpha2' => 'PR'
            ],
            [
                'en' => 'Dominican Republic',
                'cn' => '多米尼加',
                'code' => '1809',
                'alpha2' => 'DO'
            ]
        ];
    }
}
