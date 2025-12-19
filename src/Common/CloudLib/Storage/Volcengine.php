<?php

namespace BasicHub\EsCore\Common\CloudLib\Storage;

use Tos\TosClient;
use Tos\Model\PutObjectInput;
use Tos\Model\CompleteMultipartUploadInput;
use Tos\Model\CreateMultipartUploadInput;
use Tos\Model\Enum;
use Tos\Model\UploadedPart;
use Tos\Model\UploadPartInput;
use Tos\Model\CopyObjectInput;
use Tos\Model\ObjectTobeDeleted;
use Tos\Model\DeleteObjectInput;
use Tos\Model\DeleteMultiObjectsInput;
use Tos\Model\HeadObjectInput;

/**
 * @install composer require volcengine/ve-tos-php-sdk
 */
class Volcengine extends Base
{
    // 地域与端点 https://www.volcengine.com/docs/6349/107356?lang=zh
    protected $region = 'cn-guangzhou';
    protected $endpoint = '';

    // 访问密钥
    protected $ak = '';
    protected $sk = '';

    protected $bucket = '';

    protected function getClient()
    {
        return new TosClient([
            'region' => $this->region,
            'endpoint' => $this->endpoint,
            // 从环境变量中获取访问密钥
            'ak' => $this->ak,
            'sk' => $this->sk,
        ]);
    }

    /**
     * @document API https://www.volcengine.com/docs/6349/74860?lang=zh
     * @document SDK https://www.volcengine.com/docs/6349/156118?lang=zh
     * @param $file
     * @param $key
     * @param $options
     * @return mixed|string
     * @throws \Exception
     */
    public function upload($file, $key, $options = [])
    {
        try {
            $client = $this->getClient();

            $fp = fopen($file, 'r');

            $input = new PutObjectInput($this->bucket, $key, $fp);

            $output = $client->putObject($input);

            // 没异常即上传成功
            return $output->getRequestId();

        } catch (\Exception $e) {
            trace(__METHOD__ . '失败' . $e->__toString(), 'error');
            throw $e;
        } finally {
            is_resource($fp) && fclose($fp);
        }
    }

    /**
     * 将options内键值对转换为调用class方式传参
     * 例如: options数据为 ['action' => 'Joyboo', 'action-joyboo' => [xxx]], 则会调用class内的 setAction和setActionJoyboo，并将值作为参数传递
     * @param object $class
     * @param array $options
     * @return void
     */
    protected function setExtraOptions($class, array $options)
    {
        foreach ($options as $name => $value) {
            $method = 'set' . implode('', array_map('ucfirst', explode('-', strtolower(trim($name)))));
            if (method_exists($class, $method)) {
                call_user_func([$class, $method], $value);
            }
        }
    }

    /**
     * @document API https://www.volcengine.com/docs/6349/74857?lang=zh
     * @document SDK https://www.volcengine.com/docs/6349/156120?lang=zh
     * @param $file
     * @param $key
     * @param $partSize
     * @param $options
     * @return void
     * @throws \Exception
     */
    public function uploadPart($file, $key, $partSize = 10 * 1024 * 1024, $options = [])
    {
        try {
            $client = $this->getClient();

            // 步骤一：创建分片上传任务
            $input = new CreateMultipartUploadInput($this->bucket, $key);
            $input->setACL(Enum::ACLPublicRead);
            $input->setStorageClass(Enum::StorageClassStandard);

            // 设置属性
            $this->setExtraOptions($input, $options);

            $output = $client->createMultipartUpload($input);

            // 获取 UploadID
            $uploadId = $output->getUploadID();

            // 步骤二：上传多个分片
            $fileSize = filesize($file);

            $partCount = intval($fileSize / $partSize);
            if (($lastPartSize = $fileSize % $partSize) !== 0) {
                $partCount++;
            } else {
                $lastPartSize = $partSize;
            }

            $parts = [];
            for ($i = 0; $i < $partCount; $i++) {
                $partNumber = $i + 1;
                $fp = fopen($file, 'r');
                // 设置当前上传的文件起始位置
                fseek($fp, $partSize * $i, 0);
                $input = new UploadPartInput($this->bucket, $key, $uploadId, $partNumber);
                if ($i === $partCount - 1) {
                    // 处理最后一个分片
                    $input->setContentLength($lastPartSize);
                } else {
                    $input->setContentLength($partSize);
                }
                $input->setContent($fp);
                $output = $client->uploadPart($input);
                trace(sprintf('upload part number=%d succeed, requestId=%s', $partNumber, $output->getRequestId()));

                // 收集所有分片
                $parts[] = new UploadedPart($partNumber, $output->getETag());
            }

            // 步骤三：合并分片
            $input = new CompleteMultipartUploadInput($this->bucket, $key, $uploadId, $parts);
            $output = $client->completeMultipartUpload($input);
            trace(sprintf('upload part succeed, requestId=%s', $output->getRequestId()));

        }  catch (\Exception $e) {
            trace(__METHOD__ . '失败' . $e->__toString(), 'error');
            throw $e;
        } finally {
            is_resource($fp) && fclose($fp);
        }
    }

    /**
     * @document API https://www.volcengine.com/docs/6349/74859?lang=zh
     * @document SDK https://www.volcengine.com/docs/6349/156133?lang=zh
     * @param $formKey
     * @param $toKey
     * @param $options
     * @return void
     * @throws \Exception
     */
    public function copy($formKey, $toKey, $options = [])
    {
        try {
            $client = $this->getClient();

            // 只支持到同存储桶copy操作，跨存储桶涉及权限控制等，不考虑支持
            $input = new CopyObjectInput($this->bucket,  $formKey, $this->bucket, $toKey);

            $input->setACL(Enum::ACLPublicRead);
            $input->setStorageClass(Enum::StorageClassIa);

            // 设置属性
            $this->setExtraOptions($input, $options);

            if (isset($options['meta'])) {
                // 设置复制时重写目标对象元数据，仅当设置为该参数时，才能重写目标对象元数据，否则目标对象元数据会直接从源对象继承
                $input->setMetadataDirective(Enum::MetadataDirectiveReplace);
            }

            // 设置目标对象的 HTTP 标准属性
            // $input->setContentDisposition('test-disposition');
            // $input->setExpires(time() + 3600);
            // $input->setContentEncoding('test-encoding');
            // $input->setContentLanguage('test-language');
            // $input->setContentType('text/plain');

            $output = $client->copyObject($input);
            return $output->getRequestId();

        } catch (\Exception $e) {
            trace(__METHOD__ . '失败' . $e->__toString(), 'error');
            throw $e;
        }
    }

    /**
     * @document SDK https://www.volcengine.com/docs/6349/156132?lang=zh
     * @document API https://www.volcengine.com/docs/6349/74855?lang=zh
     * @document API https://www.volcengine.com/docs/6349/74857?lang=zh
     * @param $key
     * @param $options
     * @return void
     */
    public function delete($key, $options = [])
    {
        try {
            $client = $this->getClient();

            // 火山云支持批量删除
            if (is_array($key)) {
                // 批量删除对象
                $objects = [];
                foreach ($key as $item) {
                    $objects[] = new ObjectTobeDeleted($item);
                }

                $input = new DeleteMultiObjectsInput($this->bucket);
                $input->setObjects($objects);
                $output = $client->deleteMultiObjects($input);
            } else {
                $input = new DeleteObjectInput($this->bucket, $key);
                $output = $client->deleteObject($input);
            }
            return $output->getRequestId();
        }  catch (\Exception $e) {
            trace(__METHOD__ . '失败' . $e->__toString(), 'error');
            throw $e;
        }
    }

    /**
     * 火山云没有直接判断对象是否存在的方法，通过元数据判断
     * @document API https://www.volcengine.com/docs/6349/74864?lang=zh
     * @document SDK https://www.volcengine.com/docs/6349/156129?lang=zh
     * @param $key
     * @param $options
     * @return void
     */
    public function doesObjectExist($key, $options = [])
    {
        try {
            $client = $this->getClient();

            // 获取对象元数据
            $client->headObject(new HeadObjectInput($this->bucket, $key));

            return true;

        } catch (\Exception $e) {
            trace(__METHOD__ . '失败' . $e->__toString(), 'error');
            return false;
        }
    }

    /**
     * @document https://www.volcengine.com/docs/86081/1660390?lang=zh
     * @param $expire
     * @return void
     */
    public function stsUpload($expire = 3600)
    {
        $Sts = new \BasicHub\EsCore\Common\CloudLib\Sts\Volcengine($this->toArray());

        $policy = [
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => ['tos:*'], // TOS所有操作权限
                    'Resource' => ['*'], // 所有资源
                    // 可选：限制地域（如仅cn-beijing），取消注释即可
                    // 'Condition' => [
                    //     'StringEquals' => [
                    //         'volc:RequestedRegion' => [$this->config['region']]
                    //     ]
                    // ]
                ]
            ],
            'Version' => '2012-10-17' // 固定Policy版本
        ];

        $stsResponse = $Sts->get(json_encode($policy), $expire);
        $data = $stsResponse->toArray();

        // 除了基本的密钥信息之外，还需要给客户端返回对象存储信息
        $data['bucket'] = $this->bucket;
        $data['driver'] = $this->getClassName();

        $data['region'] = $this->region;

        return $data;
    }
}
