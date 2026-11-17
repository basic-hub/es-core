<?php

namespace BasicHub\EsCore\Common\CloudLib\Ssl;

interface SslInterface
{
    /**
     * 列出所有的证书
     * @param array $params
     * @return mixed
     */
    public function list($params);

    /**
     * 列出账号下所有证书（自动分页拉取）
     * @param array $params
     * @return mixed
     */
    public function listAll($params);

    /**
     * 将证书列表输出为控制台表格
     * @param array $params
     * @return mixed
     */
    public function echoTable($params);

    /**
     * 上传证书到云厂商证书管理服务
     * @param string $cert PEM格式证书内容或证书文件路径
     * @param string $key PEM格式私钥内容或私钥文件路径
     * @param array $params 其他参数（如证书名称、标签等）
     * @return mixed
     */
    public function upload($cert, $key, $params);

    /**
     * 部署/更新资源绑定的SSL证书（统一部署接口）
     * @param string $certificateId 证书ID
     * @param array $params 部署参数（资源类型、地域、实例ID列表等）
     * @return mixed
     */
    public function deployToResources($certificateId, $params);

    /**
     * 查询证书详情
     * @param string $certificateId 证书ID
     * @return mixed
     */
    public function detail($certificateId);

    /**
     * 查询证书绑定的资源列表
     * @param string $certificateId 证书ID
     * @param array $params 其他参数
     * @return mixed
     */
    public function listBindings($certificateId, $params);
}
