<?php

namespace BasicHub\EsCore\Common\CloudLib\Domain;

interface DomainInterface
{
    /**
     * 获取域名列表（单页）
     * @param array $params
     * @return mixed
     */
    public function list($params = []);

    /**
     * 获取账号下所有域名（自动分页拉取）
     * @param array $params
     * @return mixed
     */
    public function listAll($params = []);

    /**
     * 将域名列表输出为控制台表格
     * @param array $params
     * @return mixed
     */
    public function echoTable($params = []);
}
