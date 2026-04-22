<?php

namespace BasicHub\EsCore\Model;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\DbManager;

/**
 * @mixin AbstractModel
 */
trait BaseModelTrait
{
    /**
     * 分表标识
     * @var mixed|string
     */
    protected $subid = '';

    protected $sort = ['id' => 'desc'];

    /**
     * 在Extension修改器中，是否将数据合并进原字段，true-合并，false-覆盖(默认)
     * @var bool
     */
    protected $isMergeExtension = false;

    public function __construct($data = [], $tabname = '', $subid = '')
    {
        // $tabname > $this->tableName > $this->_getTable()
        $tabname && $this->tableName = $tabname;
        if ( ! $this->tableName) {
            $this->tableName = $this->_getTable();
        }

        $this->subid = $subid;

//        $this->autoTimeStamp = false;
        $this->createTime = 'instime';
        $this->updateTime = false;
        $this->setBaseTraitProtected();

        parent::__construct($data);
    }

    protected function setBaseTraitProtected()
    {
    }

    /**
     * 获取表名，并将将Java风格转换为C的风格
     * @return string
     */
    protected function _getTable()
    {
        $name = basename(str_replace('\\', '/', get_called_class()));
        return parse_name($name);
    }

    /**
     * schemaInfo() 本身有静态缓存，首次调用查一次 DB，后续都走内存。getPkFiledName() 是在已缓存的 Table 对象上遍历内存，没有额外 DB 开销，性能可以接受。
     * @return array|mixed|null
     * @throws \EasySwoole\ORM\Exception\Exception
     */
    public function getPk()
    {
        return $this->schemaInfo()->getPkFiledName();
    }

    protected function getExtensionAttr($extension = '', $alldata = [])
    {
        return is_array($extension) ? $extension : json_decode($extension, true);
    }

    /**
     * 数据写入前对extension字段的值进行处理
     * @access protected
     * @param array $extension 原数据
     * @return string|array 处理后的值
     */
    protected function setExtensionAttr($extension = [])
    {
        // QueryBuilder::func 等结构
        if (is_array($extension) && in_array(array_key_first($extension), ['[I]', '[F]', '[N]'])) {
            return $extension;
        }

        if (is_string($extension)) {
            $extension = json_decode($extension, true);
            if ( ! $extension) {
                return json_encode(new \stdClass());
            }
        }

        if ($this->isMergeExtension) {
            // 现有数据
            $ext = $this->getAttr('extension') ?: [];
            $extension = array_merge_multi($ext, $extension);
        }

        return json_encode($extension);
    }

    protected function setInstimeAttr($instime, $all)
    {
        return is_numeric($instime) ? $instime : strtotime($instime);
    }

    /**
     * 获取分表标识符
     * @return mixed|string
     */
    public function getSubid()
    {
        return $this->subid;
    }

    /**
     * 克隆携带分表标识符
     * @return AbstractModel
     */
    public function _clone(): AbstractModel
    {
        $model = parent::_clone();
        // 本为受保护属性，原理：同一个类的不同实例间可互相访问受保护或私有成员
        $model->subid = $this->subid;
        $model->isMergeExtension = $this->isMergeExtension;
        return $model;
    }

    public function setOrder(array $order = [])
    {
        $sort = $this->sort;
        // 'id desc'
        if (is_string($sort)) {
            list($sortField, $sortValue) = explode(' ', $sort);
            $order[$sortField] = $sortValue;
        } // ['sort' => 'desc'] || ['sort' => 'desc', 'id' => 'asc']
        else if (is_array($sort)) {
            // 保证传值的最高优先级
            foreach ($sort as $k => $v) {
                if ( ! isset($order[$k])) {
                    $order[$k] = $v;
                }
            }
        }

        foreach ($order as $key => $value) {
            $this->order($key, $value);
        }
        return $this;
    }

    /**
     * 不修改配置的情况下，all结果集转Collection，文档： http://www.easyswoole.com/Components/Orm/toArray.html
     * @param bool $toArray
     * @return array|bool|\EasySwoole\ORM\Collection\Collection|\EasySwoole\ORM\Db\Cursor|\EasySwoole\ORM\Db\CursorInterface
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function ormToCollection($toArray = true)
    {
        $result = $this->all();
        if ( ! $result instanceof \EasySwoole\ORM\Collection\Collection) {
            $result = new \EasySwoole\ORM\Collection\Collection($result);
        }
        return $toArray ? $result->toArray() : $result;
    }

    /**
     * 生成基本的 key=>value 结构
     * @param string $key
     * @param string $value
     * @param mixed $where 模型where，除了链式语法之外，此参数也是一种选择
     * @return array
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function getMap($key = 'id', $value = 'name', $where = null)
    {
        $result = [];
        if (!is_null($value)) {
            $this->field([$key, $value]);
        }
        $all = $this->all($where);
        foreach ($all as $item) {
            if ($item instanceof AbstractModel) {
                $item = $item->toArray();
            }
            $result[$item[$key]] = is_null($value) ? $item : $item[$value];
        }
        return $result;
    }

    /**
     * 将数据表不存在的字段都存入指定key
     * @param array $input
     * @param string $columnName
     * @param bool $assign
     * @return $this
     * @throws \EasySwoole\ORM\Exception\Exception
     */
    public function dataExtension($input = [], $columnName = 'extension', $assign = true)
    {
        $columns = array_keys($this->schemaInfo()->getColumns());

        $data = [$columnName => $input[$columnName] ?? []];
        foreach ($input as $key => $value)
        {
            if ($key === $columnName) {
                continue;
            }
            if (in_array($key, $columns)) {
                $data[$key] = $value;
            } else {
                $data[$columnName] = array_merge($data[$columnName] ?? [], $input[$columnName] ?? [], [$key => $value]);
            }
        }
        $assign && $this->data($data);
        return $this;
    }

    // 开启事务
    public function startTrans()
    {
        DbManager::getInstance()->startTransaction($this->getQueryConnection());
    }

    public function commit()
    {
        DbManager::getInstance()->commit($this->getQueryConnection());
    }

    public function rollback()
    {
        DbManager::getInstance()->rollback($this->getQueryConnection());
    }

    public function getIsMergeExtension()
    {
        return $this->isMergeExtension;
    }

    public function setIsMergeExtension(bool $isMerge)
    {
        $this->isMergeExtension = $isMerge;
        return $this;
    }
}
