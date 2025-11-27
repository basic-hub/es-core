<?php

namespace BasicHub\EsCore\Crontab\Driver;

use BasicHub\EsCore\Common\Openssl\RsaManager;

class Http implements Interfaces
{
    public function list(): array
    {
        $url = rtrim(config('CRONTAB.url'), '/') . '/api/crontab/list';
        $array = hcurl($url, $this->body(config('CRONTAB.post') ?: []));
        return $array['result'] ?? [];
    }

    public function update(int $id, int $status)
    {
        $url = rtrim(config('CRONTAB.url'), '/') . '/update';
        hcurl($url, $this->body([
            'id' => $id,
            'status' => $status
        ]));
        return true;
    }

    protected function body($data = [])
    {
        switch (strtolower($data['encry'] = config('CRONTAB.encry'))) {
            case 'rsa':
                $Rsa = RsaManager::getInstance();
                return [
                    'encry' => $data['encry'],
                    config('RSA.key') => $Rsa->publicEncrypt(json_encode($data))
                ];

            case 'md5':
                $data['time'] = time();
                $data['sign'] = self_sign($data);
                return $data;

            default:
                throw new \Exception('Error configuration: CRONTAB.encry');
        }
    }
}
