<?php


namespace BasicHub\EsCore\Model\Admin;


use BasicHub\EsCore\Common\Classes\CtxManager;
use EasySwoole\Mysqli\QueryBuilder;

trait LogSqlTrait
{
    protected function setBaseTraitProtected()
    {
        $this->autoTimeStamp = true;
    }

    /**
     * 关联
     * @return array|mixed|null
     * @throws \Throwable
     */
    public function relation()
    {
        $callback = function (QueryBuilder $query) {
            $query->fields(['id', 'username', 'realname', 'avatar', 'status']);
            return $query;
        };
        return $this->hasOne(find_model('Admin\Admin'), $callback, 'admid', 'id');
    }

    public function sqlWriteLog($sql = '')
    {
        $Request = CtxManager::getInstance()->getRequest();
        $operinfo = CtxManager::getInstance()->getOperinfo();

        $data = [
            'admid' => $operinfo['id'] ?? 0,
            'content' => $sql,
            'ip' => ip($Request)
        ];

        $this->data($data)->save();
    }
}
