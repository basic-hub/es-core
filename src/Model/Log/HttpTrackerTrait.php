<?php

namespace BasicHub\EsCore\Model\Log;

use BasicHub\EsCore\Common\Openssl\RsaManager;
use EasySwoole\HttpClient\Bean\Response;

trait HttpTrackerTrait
{
    protected function setBaseTraitProtected()
    {
        $this->connectionName = 'log';
        $this->sort = ['instime' => 'desc'];
    }

    // starttime和endtime是小数点后有四位的时间戳字符串,转为int毫秒时间戳
    protected function _trackerTime($timeStamp)
    {
        return $timeStamp ? intval($timeStamp * 1000) : $timeStamp;
    }

    public function getStartTimeAttr($val)
    {
        return $this->_trackerTime($val);
    }

    public function getEndTimeAttr($val)
    {
        return $this->_trackerTime($val);
    }

    public function getUrlAttr($val)
    {
        return urldecode($val);
    }

    public function getRequestAttr($val)
    {
        $arr = [];
        if ($json = json_decode($val, true)) {
            $arr = $json;
        }
        return array_to_std($arr);
    }

    public function getResponseAttr($val)
    {
        $json = json_decode($val, true);
        $arr = is_array($json) ? $json : [];
        return array_to_std($arr);
    }

    /**
     * 一对多关联
     * @return mixed
     */
    public function children()
    {
        return $this->hasMany(static::class, null, 'point_id', 'parent_id');
    }

    /**
     * @return Response|null
     * @throws \EasySwoole\HttpClient\Exception\InvalidUrl
     */
    public function repeatOne(): ?Response
    {
        $data = $this->toRawArray();
        $url = $data['url'];
        $request = json_decode($data['request'], true);

        // 没有header或者method说明是以前的结构，暂不处理
        if ( ! isset($request['header']) || ! isset($request['method'])) {
            return null;
        }
        $headers = [];
        foreach ($request['header'] as $hk => $hd) {
            if (is_array($hd)) {
                $hd = current($hd);
            }
            $headers[$hk] = $hd;
        }

        // UserAgent区分复发请求
        $headers['user-agent'] = ($headers['user-agent'] ?? '') . ';HttpTracker';

        // 可能不一定为POST取值，待发现更多使用场景可增加处理方式
        $body = empty($request['POST']) ? [] : [config('RSA.key') => RsaManager::getInstance()->publicEncrypt(json_encode($request['POST']))];
        return hcurl(
            $url,
            $body,
            strtolower($request['method']),
            $headers,
            ['resultType' => null],
            [
                'client_set' => [
                    'followLocation' => 0 // 禁止重定向
                ]
            ]
        );
    }
}
