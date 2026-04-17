<?php

namespace BasicHub\EsCore\Common\CloudLib\Dns;

use AlibabaCloud\SDK\Alidns\V20150109\Alidns;
use AlibabaCloud\SDK\Alidns\V20150109\Models\AddDomainRecordRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DeleteDomainRecordRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DescribeDomainRecordsRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\UpdateDomainRecordRequest;
use Darabonba\OpenApi\Models\Config;

/**
 * composer require alibabacloud/alidns-20150109 4.3.5
 * @document https://help.aliyun.com/zh/dns/api-alidns-2015-01-09-adddomainrecord?spm=a2c4g.11186623.0.0.46d771f2uzjqYA
 * @apitools https://api.aliyun.com/api/Alidns/2015-01-09/UpdateDomainRecord?spm=a2c4g.11186623.0.0.34b6690ctaYz8S
 */
class Alibaba extends Base
{
    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    private $endpoint = 'alidns.aliyuncs.com';

    private function client(): Alidns
    {
        $config = new Config([
            'accessKeyId'     => $this->accessKeyId,
            'accessKeySecret' => $this->accessKeySecret,
            'endpoint'        => $this->endpoint,
        ]);
        return new Alidns($config);
    }

    public function list(string $type = 'A', int $limit = 100): array
    {
        $request = new DescribeDomainRecordsRequest([
            'domainName'  => $this->domain,
            'pageNumber'  => 1,
            'pageSize'    => min($limit, 500),
            'typeKeyWord' => $type,
        ]);

        $resp = $this->client()->describeDomainRecords($request);
        $records = $resp->body->domainRecords->record ?? [];

        $result = [];
        foreach ($records as $record) {
            $result[$record->RR] = [
                'Name'       => $record->RR,
                'Status'     => $record->status,
                'UpdatedOn'  => $record->updateTimestamp,
                'RecordId'   => $record->recordId,
                'Value'      => $record->value,
                'RecordType' => $record->type,
                'RecordLine' => $record->line,
            ];
        }
        return $result;
    }

    public function create(array $array)
    {
        $array = array_merge(['RecordType' => 'A', 'RecordLine' => 'default'], $array);

        $params = $this->filter($array, ['RR', 'Value', 'RecordType', 'RecordLine']);

        $request = new AddDomainRecordRequest([
            'domainName' => $this->domain,
            'RR'         => $params['RR'],
            'type'       => $params['RecordType'],
            'value'      => $params['Value'],
            'line'       => $params['RecordLine'],
        ]);

        $resp = $this->client()->addDomainRecord($request);
        return $resp->body->recordId;
    }

    public function modify(array $array)
    {
        $array = array_merge(['RecordType' => 'A', 'RecordLine' => 'default'], $array);

        $params = $this->filter($array, ['RecordId', 'RR', 'Value', 'RecordType', 'RecordLine']);

        $request = new UpdateDomainRecordRequest([
            'recordId' => $params['RecordId'],
            'RR'       => $params['RR'],
            'type'     => $params['RecordType'],
            'value'    => $params['Value'],
            'line'     => $params['RecordLine'],
        ]);

        $resp = $this->client()->updateDomainRecord($request);
        return $resp->body->recordId;
    }

    public function delete(array $array)
    {
        $params = $this->filter($array, ['RecordId']);

        $request = new DeleteDomainRecordRequest([
            'recordId' => $params['RecordId'],
        ]);

        $resp = $this->client()->deleteDomainRecord($request);
        return $resp->body->requestId;
    }
}
