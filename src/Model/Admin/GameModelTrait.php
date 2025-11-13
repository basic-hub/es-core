<?php

namespace BasicHub\EsCore\Model\Admin;


use EasySwoole\ORM\AbstractModel;
use BasicHub\EsCore\Model\BaseModelTrait;

/**
 * @mixin BaseModelTrait
 */
trait GameModelTrait
{
    protected function setBaseTraitProtected()
    {
        $this->autoTimeStamp = true;
        $this->sort = ['sort' => 'asc', 'id' => 'desc'];
    }

    public static function onAfterInsert($model, $res)
    {
        if ($res) {
            $model->_delCache();
            $model->__after_write('create');
        }
    }

    public static function onAfterUpdate($model, $res)
    {
        if ($res) {
            $model->_delCache();
            $model->__after_write('update');
        }
    }

    protected function _delCache()
    {
    }

    public function getGameAll($where = [])
    {
        /* @var AbstractModel|BaseModelTrait $this */
        if ($where) {
            $this->where($where);
        }
        return $this->where('status', 1)->setOrder()->all('id');
    }

    /**
     * 获取id => name 键值对
     * @param array $idArray
     * @return array|null
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function getKeyVlaueByid($idArray = [])
    {
        if ($idArray) {
            $this->where(['id' => [$idArray, 'in']]);
        }
        return $this->getMap();
    }
}
