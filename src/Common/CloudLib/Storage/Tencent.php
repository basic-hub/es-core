<?php

namespace BasicHub\EsCore\Common\CloudLib\Storage;

use EasySwoole\Oss\Tencent\Config;
use EasySwoole\Oss\Tencent\Exception\ServiceResponseException;
use EasySwoole\Oss\Tencent\OssClient;

/**
 * composer require easyswoole/oss
 */
class Tencent extends Base
{
    /**
     * Cos Client
     * @var OssClient
     */
    private $client;

    protected $secretId;
    protected $secretKey;
    protected $region;
    protected $bucket;

    protected function initialize(): void
    {
        $config = new Config([
            'secretId' => $this->secretId,
            'secretKey' => $this->secretKey,
            'region' => $this->region,
            'bucket' => $this->bucket
        ]);
        $this->client = new OssClient($config);
    }

    /**
     * 设置桶，默认取配置
     * @param string $bucket
     * @return static
     */
    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
        return $this;
    }

    /**
     * @document https://help.aliyun.com/zh/oss/user-guide/simple-upload?spm=a2c4g.11186623.help-menu-31815.d_0_3_1_0.7a7f7dc03EDW0R&scm=20140722.H_31848._.OR_help-T_cn~zh-V_1#8f0b0bfe23baf
     * @param string $key
     * @return string
     */
    protected function getObjectKet($key)
    {
        // 编码长度最大为850个字节
        $len = strlen($key);
        if ($len > 850) {
            throw new \Exception("Tencent OSS key length too long: $len");
        }
        // 不允许以正斜线/或者反斜线\开头
        $key = ltrim($key, '/');

        $notUse = [
            // 不允许携带以下字符串：%0a、%0d
            '%0a',
            '%0d',
            // 对象键中不支持 ASCII 控制字符中的字符上(↑)，字符下(↓)，字符右(→)，字符左(←)，分别对应 CAN(24)，EM(25)，SUB(26)，ESC(27)。
            '\x18',
            '\x19',
            '\x1A',
            '\x1B',
        ];

        foreach ($notUse as $item) {
            if (strpos($key, $item) !== false) {
                throw new \Exception("Tencent The key does not comply with the rules: $key");
            }
        }

        return $key;
    }


    public function upload($file, $key, $options = [])
    {
        if ( ! is_file($file)) {
            throw new \Exception("file {$file} not exists");
        }

        try {
            $key = $this->getObjectKet($key);
            $fp = fopen($file, 'rb');

            $result = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $fp
            ]);

            return $result['Location'];
        } catch (\Exception $e) {
            trace(__METHOD__ . '上传失败' . $e->__toString(), 'error');
            throw $e;
        } finally {
            is_resource($fp) && fclose($fp);
        }
    }

    public function delete($key, $options = [])
    {
        try {
            $key = $this->getObjectKet($key);
            $result = $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => ltrim($key, '/'),
            ]);
            return $result;
        } catch (\Exception $e) {
            trace($e->getMessage(), 'error');
            throw $e;
        }
    }

    public function uploadPart($file, $key, $partSize = 10 * 1024 * 1024, $options = [])
    {
        try {
            $key = $this->getObjectKet($key);
            $fp = fopen($file, 'rb');
            $this->client->upload($this->bucket, $key, $fp, ['PartSize' => $partSize] + $options);
        } catch (\Exception $e) {
            trace(__METHOD__ . '上传失败' . $e->__toString(), 'error');
            throw $e;
        } finally {
            is_resource($fp) && fclose($fp);
        }
    }

    function doesObjectExist($key, $options = [])
    {
        try {
            $key = $this->getObjectKet($key);
            return $this->client->doesObjectExist($this->bucket, ltrim($key, '/'));
        } catch (\Exception $e) {
            trace($e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * 复制OSS对象
     * @param $formKey
     * @param $toKey
     * @param $options
     * @return void
     * @throws \Exception
     * @document https://cloud.tencent.com/document/product/436/64284
     */
    public function copy($formKey, $toKey, $options = [])
    {
        try {
            $formKey = $this->getObjectKet($formKey);
            $toKey = $this->getObjectKet($toKey);

            $copySource = [$this->bucket, 'cos', $this->region, 'myqcloud.com/' . $formKey];
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $toKey,
                'CopySource' => implode('.', $copySource)
            ]);
        } catch (\Exception $e) {
            trace($e->__toString(), 'error');
            throw $e;
        }
    }

    /**
     * 客户端直传对象存储,一般用于超超大文件
     * 1. 对象存储需要开放允许跨域 ：  安全管理 -> 允许跨域设置 *
     * 2. 对象存储需要设置Policy权限： 给cos子账号允许对象存储操作
     * @param $expire
     * @return array
     * @throws \TencentCloud\Common\Exception\TencentCloudSDKException
     */
    public function stsUpload($expire = 3600)
    {
        $Sts = new \BasicHub\EsCore\Common\CloudLib\Sts\Tencent($this->toArray());

        $policy = json_encode([
            'version' => '2.0',
            'statement' => [
                [
                    'effect' => 'allow',
                    // 那些资源权限
                    'resource' => '*',
                    // https://cloud.tencent.com/document/product/436/65935
                    'action' => [
                        // 最小粒度原则，不给所有权限
                        //'name/cos:*'

                        // 基础上传（小文件）
                        'name/cos:PutObject',

                        // 分片上传（大文件必选）
                        'name/cos:InitiateMultipartUpload',  // 初始化分片
                        'name/cos:UploadPart',               // 上传分片
                        'name/cos:CompleteMultipartUpload',  // 完成分片
                        'name/cos:AbortMultipartUpload',     // 取消分片（可选，建议保留）

                        // 断点续传
                        'name/cos:ListMultipartUploads',
                        'name/cos:ListParts',

                        // 删除权限
                        'name/cos:DeleteObject',

                        // 无需计算文件md5权限，SDK内部会校验处理
                    ],
                ],
            ],
        ]);

        $stsResponse = $Sts->get($policy, $expire);
        $data = $stsResponse->toArray();

        // 除了基本的密钥信息之外，还需要给客户端返回对象存储信息
        $data['bucket'] = $this->bucket;
        $data['driver'] = $this->getClassName();

        $data['region'] = $this->region;

        return $data;
    }
}
