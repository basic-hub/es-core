<?php

namespace BasicHub\EsCore\Common\CloudLib\Sms;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use TencentCloud\Sms\V20210111\SmsClient;

/**
 * composer require tencentcloud/sms
 */
class Tencent extends Base
{
    protected $secretId = '';

    protected $secretKey = '';

    protected $smsSdkAppId = '';

    protected $templateId = '';

    protected $signName = '';

    protected $phoneNumberSet = [];

    protected $templateParamSet = '';

    /**
     * 地域：
     * @document https://cloud.tencent.com/document/api/213/15692#.E5.9C.B0.E5.9F.9F.E5.88.97.E8.A1.A8
     * 要顺便检查下该地域的推送域名是否存在,格式为 ses.[region].tencentcloudapi.com
     * @document https://cloud.tencent.com/document/api/1288/51055
     * @var string
     */
    protected $region = '';

    protected function getTemplateId()
    {
        return $this->getTplId($this->templateId);
    }

    /**
     * @document https://cloud.tencent.com/document/product/382/55981
     * @param string|array $to
     * @param array $params
     * @return bool
     */
    public function send($to = [], array $params = [])
    {
        if ($this->isdebug) {
            trace(__METHOD__ . ', to=' . var_export($to, true) . ', params=' . var_export($params, true));
            return true;
        }

        $this->phoneNumberSet = is_string($to) ? [$to] : $to;

        $params = array_values(array_map('strval', $params));
        $this->templateParamSet = $params;

        try {
            $Request = new SendSmsRequest();
            $Request->fromJsonString(json_encode([
                'PhoneNumberSet' => $this->phoneNumberSet,
                'SmsSdkAppId' => $this->smsSdkAppId,
                'TemplateId' => $this->getTemplateId(),
                'SignName' => $this->signName ?: null,
                'TemplateParamSet' => $this->templateParamSet,
            ]));

            $endFn = http_tracker('SDK:SMS', [
                'url' => '__TENCENT_SMS__',
                'POST' => $Request->serialize(),
                'method' => 'POST',
            ]);

            $Credential = new Credential($this->secretId, $this->secretKey);
            $Client = new SmsClient($Credential, $this->region);

            $resp = $Client->SendSms($Request);

            $str = $resp->toJsonString();
            $array = json_decode($str, true);

            $endFn($array);

            if (isset($array['Error'])) {
                notice("腾讯云发送失败1: $str");
                return false;
            }
            return true;
        } catch (TencentCloudSDKException $e) {
            $msg = '腾讯云短信发送失败2: ' . $e->__toString() . ', 号码: ' . (is_array($this->phoneNumberSet) ? implode(',', $this->phoneNumberSet) : $this->phoneNumberSet);
            is_callable($endFn) && $endFn($msg, $e->getCode());

            trace($msg, 'error');

            if (!in_array($e->getErrorCode(), [
                'InvalidParameterValue.IncorrectPhoneNumber',
                'MissingParameter.EmptyPhoneNumberSet',
            ])) {
                notice($msg);
            }

            return false;
        }
    }

    /**
     * 应根据腾讯地区码调整  https://cloud.tencent.com/document/product/382/18051
     * @return array[]
     */
    public static function codeList() {
        return [
            [
                'en' => 'China',
                'cn' => '中国大陆',
                'code' => '86',
                'alpha2' => 'CN'
            ],
            [
                'en' => 'Ascension Island',
                'cn' => '阿森松岛',
                'code' => '247',
                'alpha2' => 'AC',
            ],
            [
                'en' => 'Andorra',
                'cn' => '安道尔',
                'code' => '376',
                'alpha2' => 'AD',
            ],
            [
                'en' => 'United Arab Emirates',
                'cn' => '阿拉伯联合酋长国',
                'code' => '971',
                'alpha2' => 'AE',
            ],
            [
                'en' => 'Afghanistan',
                'cn' => '阿富汗',
                'code' => '93',
                'alpha2' => 'AF',
            ],
            [
                'en' => 'Antigua and Barbuda',
                'cn' => '安提瓜和巴布达',
                'code' => '1268',
                'alpha2' => 'AG',
            ],
            [
                'en' => 'Anguilla',
                'cn' => '安圭拉',
                'code' => '1264',
                'alpha2' => 'AI',
            ],
            [
                'en' => 'Albania',
                'cn' => '阿尔巴尼亚',
                'code' => '355',
                'alpha2' => 'AL',
            ],
            [
                'en' => 'Armenia',
                'cn' => '亚美尼亚',
                'code' => '374',
                'alpha2' => 'AM',
            ],
            [
                'en' => 'Netherlands Antilles',
                'cn' => '荷属安的列斯',
                'code' => '599',
                'alpha2' => 'AN',
            ],
            [
                'en' => 'Angola',
                'cn' => '安哥拉',
                'code' => '244',
                'alpha2' => 'AO',
            ],
            [
                'en' => 'Argentina',
                'cn' => '阿根廷',
                'code' => '54',
                'alpha2' => 'AR',
            ],
            [
                'en' => 'American Samoa',
                'cn' => '美属萨摩亚',
                'code' => '1684',
                'alpha2' => 'AS',
            ],
            [
                'en' => 'Austria',
                'cn' => '奥地利',
                'code' => '43',
                'alpha2' => 'AT',
            ],
            [
                'en' => 'Australia',
                'cn' => '澳大利亚',
                'code' => '61',
                'alpha2' => 'AU',
            ],
            [
                'en' => 'Aruba',
                'cn' => '阿鲁巴',
                'code' => '297',
                'alpha2' => 'AW',
            ],
            [
                'en' => 'Azerbaijan',
                'cn' => '阿塞拜疆',
                'code' => '994',
                'alpha2' => 'AZ',
            ],
            [
                'en' => 'Bosnia and Herzegovina',
                'cn' => '波斯尼亚和黑塞哥维那',
                'code' => '387',
                'alpha2' => 'BA',
            ],
            [
                'en' => 'Barbados',
                'cn' => '巴巴多斯',
                'code' => '1246',
                'alpha2' => 'BB',
            ],
            [
                'en' => 'Bangladesh',
                'cn' => '孟加拉国',
                'code' => '880',
                'alpha2' => 'BD',
            ],
            [
                'en' => 'Belgium',
                'cn' => '比利时',
                'code' => '32',
                'alpha2' => 'BE',
            ],
            [
                'en' => 'Burkina Faso',
                'cn' => '布基纳法索',
                'code' => '226',
                'alpha2' => 'BF',
            ],
            [
                'en' => 'Bulgaria',
                'cn' => '保加利亚',
                'code' => '359',
                'alpha2' => 'BG',
            ],
            [
                'en' => 'Bahrain',
                'cn' => '巴林',
                'code' => '973',
                'alpha2' => 'BH',
            ],
            [
                'en' => 'Burundi',
                'cn' => '布隆迪',
                'code' => '257',
                'alpha2' => 'BI',
            ],
            [
                'en' => 'Benin',
                'cn' => '贝宁',
                'code' => '229',
                'alpha2' => 'BJ',
            ],
            [
                'en' => 'Bermuda',
                'cn' => '百慕大群岛',
                'code' => '1441',
                'alpha2' => 'BM',
            ],
            [
                'en' => 'Brunei',
                'cn' => '文莱',
                'code' => '673',
                'alpha2' => 'BN',
            ],
            [
                'en' => 'Bolivia',
                'cn' => '玻利维亚',
                'code' => '591',
                'alpha2' => 'BO',
            ],
            [
                'en' => 'Caribisch Nederland',
                'cn' => '荷兰加勒比',
                'code' => '599',
                'alpha2' => 'BQ',
            ],
            [
                'en' => 'Brazil',
                'cn' => '巴西',
                'code' => '55',
                'alpha2' => 'BR',
            ],
            [
                'en' => 'Bahamas',
                'cn' => '巴哈马',
                'code' => '1242',
                'alpha2' => 'BS',
            ],
            [
                'en' => 'Bhutan',
                'cn' => '不丹',
                'code' => '975',
                'alpha2' => 'BT',
            ],
            [
                'en' => 'Botswana',
                'cn' => '博茨瓦纳',
                'code' => '267',
                'alpha2' => 'BW',
            ],
            [
                'en' => 'Belarus',
                'cn' => '白俄罗斯',
                'code' => '375',
                'alpha2' => 'BY',
            ],
            [
                'en' => 'Belize',
                'cn' => '伯利兹',
                'code' => '501',
                'alpha2' => 'BZ',
            ],
            [
                'en' => 'Canada',
                'cn' => '加拿大',
                'code' => '1',
                'alpha2' => 'CA',
            ],
            [
                'en' => 'Democratic Republic of the Congo',
                'cn' => '刚果民主共和国',
                'code' => '243',
                'alpha2' => 'CD',
            ],
            [
                'en' => 'Central African Republic',
                'cn' => '中非共和国',
                'code' => '236',
                'alpha2' => 'CF',
            ],
            [
                'en' => 'Republic of the Congo',
                'cn' => '刚果共和国',
                'code' => '242',
                'alpha2' => 'CG',
            ],
            [
                'en' => 'Switzerland',
                'cn' => '瑞士',
                'code' => '41',
                'alpha2' => 'CH',
            ],
            [
                'en' => 'Ivory Coast',
                'cn' => '象牙海岸',
                'code' => '225',
                'alpha2' => 'CI',
            ],
            [
                'en' => 'Cook Islands',
                'cn' => '库克群岛',
                'code' => '682',
                'alpha2' => 'CK',
            ],
            [
                'en' => 'Chile',
                'cn' => '智利',
                'code' => '56',
                'alpha2' => 'CL',
            ],
            [
                'en' => 'Cameroon',
                'cn' => '喀麦隆',
                'code' => '237',
                'alpha2' => 'CM',
            ],
            [
                'en' => 'Colombia',
                'cn' => '哥伦比亚',
                'code' => '57',
                'alpha2' => 'CO',
            ],
            [
                'en' => 'Costa Rica',
                'cn' => '哥斯达黎加',
                'code' => '506',
                'alpha2' => 'CR',
            ],
            [
                'en' => 'Cape Verde',
                'cn' => '佛得角',
                'code' => '238',
                'alpha2' => 'CV',
            ],
            [
                'en' => 'Curacao',
                'cn' => '库拉索',
                'code' => '599',
                'alpha2' => 'CW',
            ],
            [
                'en' => 'Cyprus',
                'cn' => '塞浦路斯',
                'code' => '357',
                'alpha2' => 'CY',
            ],
            [
                'en' => 'Czech',
                'cn' => '捷克共和国',
                'code' => '420',
                'alpha2' => 'CZ',
            ],
            [
                'en' => 'Germany',
                'cn' => '德国',
                'code' => '49',
                'alpha2' => 'DE',
            ],
            [
                'en' => 'Djibouti',
                'cn' => '吉布提',
                'code' => '253',
                'alpha2' => 'DJ',
            ],
            [
                'en' => 'Denmark',
                'cn' => '丹麦',
                'code' => '45',
                'alpha2' => 'DK',
            ],
            [
                'en' => 'Dominica',
                'cn' => '多米尼加',
                'code' => '1767',
                'alpha2' => 'DM',
            ],
            [
                'en' => 'Dominican Republic',
                'cn' => '多米尼加共和国',
                'code' => '1809',
                'alpha2' => 'DO',
            ],
            [
                'en' => 'Algeria',
                'cn' => '阿尔及利亚',
                'code' => '213',
                'alpha2' => 'DZ',
            ],
            [
                'en' => 'Ecuador',
                'cn' => '厄瓜多尔',
                'code' => '593',
                'alpha2' => 'EC',
            ],
            [
                'en' => 'Estonia',
                'cn' => '爱沙尼亚',
                'code' => '372',
                'alpha2' => 'EE',
            ],
            [
                'en' => 'Egypt',
                'cn' => '埃及',
                'code' => '20',
                'alpha2' => 'EG',
            ],
            [
                'en' => 'Western Sahara',
                'cn' => '西撒哈拉',
                'code' => '212',
                'alpha2' => 'EH',
            ],
            [
                'en' => 'Eritrea',
                'cn' => '厄立特里亚',
                'code' => '291',
                'alpha2' => 'ER',
            ],
            [
                'en' => 'Spain',
                'cn' => '西班牙',
                'code' => '34',
                'alpha2' => 'ES',
            ],
            [
                'en' => 'Ethiopia',
                'cn' => '埃塞俄比亚',
                'code' => '251',
                'alpha2' => 'ET',
            ],
            [
                'en' => 'Finland',
                'cn' => '芬兰',
                'code' => '358',
                'alpha2' => 'FI',
            ],
            [
                'en' => 'Fiji',
                'cn' => '斐济',
                'code' => '679',
                'alpha2' => 'FJ',
            ],
            [
                'en' => 'Micronesia',
                'cn' => '密克罗尼西亚',
                'code' => '691',
                'alpha2' => 'FM',
            ],
            [
                'en' => 'Faroe Islands',
                'cn' => '法罗群岛',
                'code' => '298',
                'alpha2' => 'FO',
            ],
            [
                'en' => 'France',
                'cn' => '法国',
                'code' => '33',
                'alpha2' => 'FR',
            ],
            [
                'en' => 'Gabon',
                'cn' => '加蓬',
                'code' => '241',
                'alpha2' => 'GA',
            ],
            [
                'en' => 'United Kingdom',
                'cn' => '英国',
                'code' => '44',
                'alpha2' => 'GB',
            ],
            [
                'en' => 'Grenada',
                'cn' => '格林纳达',
                'code' => '1473',
                'alpha2' => 'GD',
            ],
            [
                'en' => 'Georgia',
                'cn' => '格鲁吉亚',
                'code' => '995',
                'alpha2' => 'GE',
            ],
            [
                'en' => 'French Guiana',
                'cn' => '法属圭亚那',
                'code' => '594',
                'alpha2' => 'GF',
            ],
            [
                'en' => 'Guernsey',
                'cn' => '根西岛',
                'code' => '826',
                'alpha2' => 'GG',
            ],
            [
                'en' => 'Ghana',
                'cn' => '加纳',
                'code' => '233',
                'alpha2' => 'GH',
            ],
            [
                'en' => 'Gibraltar',
                'cn' => '直布罗陀',
                'code' => '350',
                'alpha2' => 'GI',
            ],
            [
                'en' => 'Greenland',
                'cn' => '格陵兰岛',
                'code' => '299',
                'alpha2' => 'GL',
            ],
            [
                'en' => 'Gambia',
                'cn' => '冈比亚',
                'code' => '220',
                'alpha2' => 'GM',
            ],
            [
                'en' => 'Guinea',
                'cn' => '几内亚',
                'code' => '224',
                'alpha2' => 'GN',
            ],
            [
                'en' => 'Guadeloupe',
                'cn' => '瓜德罗普岛',
                'code' => '590',
                'alpha2' => 'GP',
            ],
            [
                'en' => 'Equatorial Guinea',
                'cn' => '赤道几内亚',
                'code' => '240',
                'alpha2' => 'GQ',
            ],
            [
                'en' => 'Greece',
                'cn' => '希腊',
                'code' => '30',
                'alpha2' => 'GR',
            ],
            [
                'en' => 'Guatemala',
                'cn' => '瓜地马拉',
                'code' => '502',
                'alpha2' => 'GT',
            ],
            [
                'en' => 'Guam',
                'cn' => '关岛',
                'code' => '1671',
                'alpha2' => 'GU',
            ],
            [
                'en' => 'Guinea-Bissau',
                'cn' => '几内亚比绍共和国',
                'code' => '245',
                'alpha2' => 'GW',
            ],
            [
                'en' => 'Guyana',
                'cn' => '圭亚那',
                'code' => '592',
                'alpha2' => 'GY',
            ],
            [
                'en' => 'Hong Kong',
                'cn' => '中国香港',
                'code' => '852',
                'alpha2' => 'HK',
            ],
            [
                'en' => 'Honduras',
                'cn' => '洪都拉斯',
                'code' => '504',
                'alpha2' => 'HN',
            ],
            [
                'en' => 'Croatia',
                'cn' => '克罗地亚',
                'code' => '385',
                'alpha2' => 'HR',
            ],
            [
                'en' => 'Haiti',
                'cn' => '海地',
                'code' => '509',
                'alpha2' => 'HT',
            ],
            [
                'en' => 'Hungary',
                'cn' => '匈牙利',
                'code' => '36',
                'alpha2' => 'HU',
            ],
            [
                'en' => 'Indonesia',
                'cn' => '印度尼西亚',
                'code' => '62',
                'alpha2' => 'ID',
            ],
            [
                'en' => 'Ireland',
                'cn' => '爱尔兰',
                'code' => '353',
                'alpha2' => 'IE',
            ],
            [
                'en' => 'Israel',
                'cn' => '以色列',
                'code' => '972',
                'alpha2' => 'IL',
            ],
            [
                'en' => 'Isle of Man',
                'cn' => '马恩岛',
                'code' => '833',
                'alpha2' => 'IM',
            ],
            [
                'en' => 'India',
                'cn' => '印度',
                'code' => '91',
                'alpha2' => 'IN',
            ],
            [
                'en' => 'Iraq',
                'cn' => '伊拉克',
                'code' => '964',
                'alpha2' => 'IQ',
            ],
            [
                'en' => 'Iceland',
                'cn' => '冰岛',
                'code' => '354',
                'alpha2' => 'IS',
            ],
            [
                'en' => 'Italy',
                'cn' => '意大利',
                'code' => '39',
                'alpha2' => 'IT',
            ],
            [
                'en' => 'Jersey',
                'cn' => '泽西岛',
                'code' => '832',
                'alpha2' => 'JE',
            ],
            [
                'en' => 'Jamaica',
                'cn' => '牙买加',
                'code' => '1876',
                'alpha2' => 'JM',
            ],
            [
                'en' => 'Jordan',
                'cn' => '约旦',
                'code' => '962',
                'alpha2' => 'JO',
            ],
            [
                'en' => 'Japan',
                'cn' => '日本',
                'code' => '81',
                'alpha2' => 'JP',
            ],
            [
                'en' => 'Kenya',
                'cn' => '肯尼亚',
                'code' => '254',
                'alpha2' => 'KE',
            ],
            [
                'en' => 'Kyrgyzstan',
                'cn' => '吉尔吉斯斯坦',
                'code' => '996',
                'alpha2' => 'KG',
            ],
            [
                'en' => 'Cambodia',
                'cn' => '柬埔寨',
                'code' => '855',
                'alpha2' => 'KH',
            ],
            [
                'en' => 'Kiribati',
                'cn' => '基里巴斯',
                'code' => '686',
                'alpha2' => 'KI',
            ],
            [
                'en' => 'Comoros',
                'cn' => '科摩罗',
                'code' => '269',
                'alpha2' => 'KM',
            ],
            [
                'en' => 'Saint Kitts and Nevis',
                'cn' => '圣基茨和尼维斯',
                'code' => '1869',
                'alpha2' => 'KN',
            ],
            [
                'en' => 'South Korea',
                'cn' => '韩国',
                'code' => '82',
                'alpha2' => 'KR',
            ],
            [
                'en' => 'Kuwait',
                'cn' => '科威特',
                'code' => '965',
                'alpha2' => 'KW',
            ],
            [
                'en' => 'Cayman Islands',
                'cn' => '开曼群岛',
                'code' => '1345',
                'alpha2' => 'KY',
            ],
            [
                'en' => 'Kazakhstan',
                'cn' => '哈萨克斯坦',
                'code' => '7',
                'alpha2' => 'KZ',
            ],
            [
                'en' => 'Laos',
                'cn' => '老挝',
                'code' => '856',
                'alpha2' => 'LA',
            ],
            [
                'en' => 'Lebanon',
                'cn' => '黎巴嫩',
                'code' => '961',
                'alpha2' => 'LB',
            ],
            [
                'en' => 'Saint Lucia',
                'cn' => '圣露西亚',
                'code' => '1758',
                'alpha2' => 'LC',
            ],
            [
                'en' => 'Liechtenstein',
                'cn' => '列支敦士登',
                'code' => '423',
                'alpha2' => 'LI',
            ],
            [
                'en' => 'Sri Lanka',
                'cn' => '斯里兰卡',
                'code' => '94',
                'alpha2' => 'LK',
            ],
            [
                'en' => 'Liberia',
                'cn' => '利比里亚',
                'code' => '231',
                'alpha2' => 'LR',
            ],
            [
                'en' => 'Lesotho',
                'cn' => '莱索托',
                'code' => '266',
                'alpha2' => 'LS',
            ],
            [
                'en' => 'Lithuania',
                'cn' => '立陶宛',
                'code' => '370',
                'alpha2' => 'LT',
            ],
            [
                'en' => 'Luxembourg',
                'cn' => '卢森堡',
                'code' => '352',
                'alpha2' => 'LU',
            ],
            [
                'en' => 'Latvia',
                'cn' => '拉脱维亚',
                'code' => '371',
                'alpha2' => 'LV',
            ],
            [
                'en' => 'Libya',
                'cn' => '利比亚',
                'code' => '218',
                'alpha2' => 'LY',
            ],
            [
                'en' => 'Morocco',
                'cn' => '摩洛哥',
                'code' => '212',
                'alpha2' => 'MA',
            ],
            [
                'en' => 'Monaco',
                'cn' => '摩纳哥',
                'code' => '377',
                'alpha2' => 'MC',
            ],
            [
                'en' => 'Moldova',
                'cn' => '摩尔多瓦',
                'code' => '373',
                'alpha2' => 'MD',
            ],
            [
                'en' => 'Montenegro',
                'cn' => '黑山',
                'code' => '382',
                'alpha2' => 'ME',
            ],
            [
                'en' => 'Madagascar',
                'cn' => '马达加斯加',
                'code' => '261',
                'alpha2' => 'MG',
            ],
            [
                'en' => 'Marshall Islands',
                'cn' => '马绍尔群岛',
                'code' => '692',
                'alpha2' => 'MH',
            ],
            [
                'en' => 'Macedonia',
                'cn' => '马其顿',
                'code' => '389',
                'alpha2' => 'MK',
            ],
            [
                'en' => 'Mali',
                'cn' => '马里',
                'code' => '223',
                'alpha2' => 'ML',
            ],
            [
                'en' => 'Myanmar',
                'cn' => '缅甸',
                'code' => '95',
                'alpha2' => 'MM',
            ],
            [
                'en' => 'Mongolia',
                'cn' => '蒙古',
                'code' => '976',
                'alpha2' => 'MN',
            ],
            [
                'en' => 'Macao',
                'cn' => '中国澳门',
                'code' => '853',
                'alpha2' => 'MO',
            ],
            [
                'en' => 'Northern Mariana Islands',
                'cn' => '北马利安纳群岛',
                'code' => '1670',
                'alpha2' => 'MP',
            ],
            [
                'en' => 'Martinique',
                'cn' => '马丁尼克',
                'code' => '596',
                'alpha2' => 'MQ',
            ],
            [
                'en' => 'Mauritania',
                'cn' => '毛里塔尼亚',
                'code' => '222',
                'alpha2' => 'MR',
            ],
            [
                'en' => 'Montserrat',
                'cn' => '蒙特塞拉特岛',
                'code' => '1664',
                'alpha2' => 'MS',
            ],
            [
                'en' => 'Malta',
                'cn' => '马耳他',
                'code' => '356',
                'alpha2' => 'MT',
            ],
            [
                'en' => 'Mauritius',
                'cn' => '毛里求斯',
                'code' => '230',
                'alpha2' => 'MU',
            ],
            [
                'en' => 'Maldives',
                'cn' => '马尔代夫',
                'code' => '960',
                'alpha2' => 'MV',
            ],
            [
                'en' => 'Malawi',
                'cn' => '马拉维',
                'code' => '265',
                'alpha2' => 'MW',
            ],
            [
                'en' => 'Mexico',
                'cn' => '墨西哥',
                'code' => '52',
                'alpha2' => 'MX',
            ],
            [
                'en' => 'Malaysia',
                'cn' => '马来西亚',
                'code' => '60',
                'alpha2' => 'MY',
            ],
            [
                'en' => 'Mozambique',
                'cn' => '莫桑比克',
                'code' => '258',
                'alpha2' => 'MZ',
            ],
            [
                'en' => 'Namibia',
                'cn' => '纳米比亚',
                'code' => '264',
                'alpha2' => 'NA',
            ],
            [
                'en' => 'New Caledonia',
                'cn' => '新喀里多尼亚',
                'code' => '687',
                'alpha2' => 'NC',
            ],
            [
                'en' => 'Niger',
                'cn' => '尼日尔',
                'code' => '227',
                'alpha2' => 'NE',
            ],
            [
                'en' => 'Norfolk Island',
                'cn' => '诺福克岛',
                'code' => '574',
                'alpha2' => 'NF',
            ],
            [
                'en' => 'Nigeria',
                'cn' => '尼日利亚',
                'code' => '234',
                'alpha2' => 'NG',
            ],
            [
                'en' => 'Nicaragua',
                'cn' => '尼加拉瓜',
                'code' => '505',
                'alpha2' => 'NI',
            ],
            [
                'en' => 'Netherlands',
                'cn' => '荷兰',
                'code' => '31',
                'alpha2' => 'NL',
            ],
            [
                'en' => 'Norway',
                'cn' => '挪威',
                'code' => '47',
                'alpha2' => 'NO',
            ],
            [
                'en' => 'Nepal',
                'cn' => '尼泊尔',
                'code' => '977',
                'alpha2' => 'NP',
            ],
            [
                'en' => 'Nauru',
                'cn' => '瑙鲁',
                'code' => '674',
                'alpha2' => 'NR',
            ],
            [
                'en' => 'Niue',
                'cn' => '纽埃',
                'code' => '683',
                'alpha2' => 'NU',
            ],
            [
                'en' => 'New Zealand',
                'cn' => '新西兰',
                'code' => '64',
                'alpha2' => 'NZ',
            ],
            [
                'en' => 'Oman',
                'cn' => '阿曼',
                'code' => '968',
                'alpha2' => 'OM',
            ],
            [
                'en' => 'Panama',
                'cn' => '巴拿马',
                'code' => '507',
                'alpha2' => 'PA',
            ],
            [
                'en' => 'Peru',
                'cn' => '秘鲁',
                'code' => '51',
                'alpha2' => 'PE',
            ],
            [
                'en' => 'French Polynesia',
                'cn' => '法属波利尼西亚',
                'code' => '689',
                'alpha2' => 'PF',
            ],
            [
                'en' => 'Papua New Guinea',
                'cn' => '巴布亚新几内亚',
                'code' => '675',
                'alpha2' => 'PG',
            ],
            [
                'en' => 'Philippines',
                'cn' => '菲律宾',
                'code' => '63',
                'alpha2' => 'PH',
            ],
            [
                'en' => 'Pakistan',
                'cn' => '巴基斯坦',
                'code' => '92',
                'alpha2' => 'PK',
            ],
            [
                'en' => 'Poland',
                'cn' => '波兰',
                'code' => '48',
                'alpha2' => 'PL',
            ],
            [
                'en' => 'Saint Pierre and Miquelon',
                'cn' => '圣彼埃尔和密克隆岛',
                'code' => '508',
                'alpha2' => 'PM',
            ],
            [
                'en' => 'Puerto Rico',
                'cn' => '波多黎各',
                'code' => '1787',
                'alpha2' => 'PR',
            ],
            [
                'en' => 'Palestine',
                'cn' => '巴勒斯坦',
                'code' => '970',
                'alpha2' => 'PS',
            ],
            [
                'en' => 'Portugal',
                'cn' => '葡萄牙',
                'code' => '351',
                'alpha2' => 'PT',
            ],
            [
                'en' => 'Palau',
                'cn' => '帕劳',
                'code' => '680',
                'alpha2' => 'PW',
            ],
            [
                'en' => 'Paraguay',
                'cn' => '巴拉圭',
                'code' => '595',
                'alpha2' => 'PY',
            ],
            [
                'en' => 'Qatar',
                'cn' => '卡塔尔',
                'code' => '974',
                'alpha2' => 'QA',
            ],
            [
                'en' => 'Réunion Island',
                'cn' => '留尼汪',
                'code' => '262',
                'alpha2' => 'RE',
            ],
            [
                'en' => 'Romania',
                'cn' => '罗马尼亚',
                'code' => '40',
                'alpha2' => 'RO',
            ],
            [
                'en' => 'Serbia',
                'cn' => '塞尔维亚',
                'code' => '381',
                'alpha2' => 'RS',
            ],
            [
                'en' => 'Rwanda',
                'cn' => '卢旺达',
                'code' => '250',
                'alpha2' => 'RW',
            ],
            [
                'en' => 'Saudi Arabia',
                'cn' => '沙特阿拉伯',
                'code' => '966',
                'alpha2' => 'SA',
            ],
            [
                'en' => 'Solomon Islands',
                'cn' => '所罗门群岛',
                'code' => '677',
                'alpha2' => 'SB',
            ],
            [
                'en' => 'Seychelles',
                'cn' => '塞舌尔',
                'code' => '248',
                'alpha2' => 'SC',
            ],
            [
                'en' => 'Sudan',
                'cn' => '苏丹',
                'code' => '249',
                'alpha2' => 'SD',
            ],
            [
                'en' => 'Sweden',
                'cn' => '瑞典',
                'code' => '46',
                'alpha2' => 'SE',
            ],
            [
                'en' => 'Singapore',
                'cn' => '新加坡',
                'code' => '65',
                'alpha2' => 'SG',
            ],
            [
                'en' => 'Slovenia',
                'cn' => '斯洛文尼亚',
                'code' => '386',
                'alpha2' => 'SI',
            ],
            [
                'en' => 'Slovakia',
                'cn' => '斯洛伐克',
                'code' => '421',
                'alpha2' => 'SK',
            ],
            [
                'en' => 'Sierra Leone',
                'cn' => '塞拉利昂',
                'code' => '232',
                'alpha2' => 'SL',
            ],
            [
                'en' => 'San Marino',
                'cn' => '圣马力诺',
                'code' => '378',
                'alpha2' => 'SM',
            ],
            [
                'en' => 'Senegal',
                'cn' => '塞内加尔',
                'code' => '221',
                'alpha2' => 'SN',
            ],
            [
                'en' => 'Somalia',
                'cn' => '索马里',
                'code' => '252',
                'alpha2' => 'SO',
            ],
            [
                'en' => 'Suriname',
                'cn' => '苏里南',
                'code' => '597',
                'alpha2' => 'SR',
            ],
            [
                'en' => 'South Sudan',
                'cn' => '南苏丹',
                'code' => '211',
                'alpha2' => 'SS',
            ],
            [
                'en' => 'Sao Tome and Principe',
                'cn' => '圣多美和普林西比',
                'code' => '239',
                'alpha2' => 'ST',
            ],
            [
                'en' => 'El Salvador',
                'cn' => '萨尔瓦多',
                'code' => '503',
                'alpha2' => 'SV',
            ],
            [
                'en' => 'Sint Maarten (Dutch Part)',
                'cn' => '荷属圣马丁',
                'code' => '1721',
                'alpha2' => 'SX',
            ],
            [
                'en' => 'Swaziland',
                'cn' => '斯威士兰',
                'code' => '268',
                'alpha2' => 'SZ',
            ],
            [
                'en' => 'Turks and Caicos Islands',
                'cn' => '特克斯和凯科斯群岛',
                'code' => '1649',
                'alpha2' => 'TC',
            ],
            [
                'en' => 'Chad',
                'cn' => '乍得',
                'code' => '235',
                'alpha2' => 'TD',
            ],
            [
                'en' => 'Togo',
                'cn' => '多哥',
                'code' => '228',
                'alpha2' => 'TG',
            ],
            [
                'en' => 'Thailand',
                'cn' => '泰国',
                'code' => '66',
                'alpha2' => 'TH',
            ],
            [
                'en' => 'Tajikistan',
                'cn' => '塔吉克斯坦',
                'code' => '992',
                'alpha2' => 'TJ',
            ],
            [
                'en' => 'Tokelau',
                'cn' => '托克劳',
                'code' => '690',
                'alpha2' => 'TK',
            ],
            [
                'en' => 'East Timor',
                'cn' => '东帝汶',
                'code' => '670',
                'alpha2' => 'TL',
            ],
            [
                'en' => 'Turkmenistan',
                'cn' => '土库曼斯坦',
                'code' => '993',
                'alpha2' => 'TM',
            ],
            [
                'en' => 'Tunisia',
                'cn' => '突尼斯',
                'code' => '216',
                'alpha2' => 'TN',
            ],
            [
                'en' => 'Tonga',
                'cn' => '汤加',
                'code' => '676',
                'alpha2' => 'TO',
            ],
            [
                'en' => 'Türkiye',
                'cn' => '土耳其',
                'code' => '90',
                'alpha2' => 'TR',
            ],
            [
                'en' => 'Trinidad and Tobago',
                'cn' => '特立尼达和多巴哥',
                'code' => '1868',
                'alpha2' => 'TT',
            ],
            [
                'en' => 'Tuvalu',
                'cn' => '图瓦卢',
                'code' => '688',
                'alpha2' => 'TV',
            ],
            [
                'en' => 'Taiwan',
                'cn' => '中国台湾',
                'code' => '886',
                'alpha2' => 'TW',
            ],
            [
                'en' => 'Tanzania',
                'cn' => '坦桑尼亚',
                'code' => '255',
                'alpha2' => 'TZ',
            ],
            [
                'en' => 'Uganda',
                'cn' => '乌干达',
                'code' => '256',
                'alpha2' => 'UG',
            ],
            [
                'en' => 'United States',
                'cn' => '美国',
                'code' => '1',
                'alpha2' => 'US',
            ],
            [
                'en' => 'Uruguay',
                'cn' => '乌拉圭',
                'code' => '598',
                'alpha2' => 'UY',
            ],
            [
                'en' => 'Uzbekistan',
                'cn' => '乌兹别克斯坦',
                'code' => '998',
                'alpha2' => 'UZ',
            ],
            [
                'en' => 'Saint Vincent and the Grenadines',
                'cn' => '圣文森特和格林纳丁斯',
                'code' => '1784',
                'alpha2' => 'VC',
            ],
            [
                'en' => 'Venezuela',
                'cn' => '委内瑞拉',
                'code' => '58',
                'alpha2' => 'VE',
            ],
            [
                'en' => 'British Virgin Islands',
                'cn' => '英属处女群岛',
                'code' => '1284',
                'alpha2' => 'VG',
            ],
            [
                'en' => 'United States Virgin Islands',
                'cn' => '美属维尔京群岛',
                'code' => '1340',
                'alpha2' => 'VI',
            ],
            [
                'en' => 'Vietnam',
                'cn' => '越南',
                'code' => '84',
                'alpha2' => 'VN',
            ],
            [
                'en' => 'Vanuatu',
                'cn' => '瓦努阿图',
                'code' => '678',
                'alpha2' => 'VU',
            ],
            [
                'en' => 'Samoa',
                'cn' => '萨摩亚',
                'code' => '685',
                'alpha2' => 'WS',
            ],
            [
                'en' => 'Kosovo',
                'cn' => '科索沃共和国',
                'code' => '383',
                'alpha2' => 'XK',
            ],
            [
                'en' => 'Yemen',
                'cn' => '也门',
                'code' => '967',
                'alpha2' => 'YE',
            ],
            [
                'en' => 'Mayotte',
                'cn' => '马约特',
                'code' => '269',
                'alpha2' => 'YT',
            ],
            [
                'en' => 'South Africa',
                'cn' => '南非',
                'code' => '27',
                'alpha2' => 'ZA',
            ],
            [
                'en' => 'Zambia',
                'cn' => '赞比亚',
                'code' => '260',
                'alpha2' => 'ZM',
            ],
            [
                'en' => 'Zimbabwe',
                'cn' => '津巴布韦',
                'code' => '263',
                'alpha2' => 'ZW',
            ],
        ];
    }
}
