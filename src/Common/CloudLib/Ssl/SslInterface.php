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
}
