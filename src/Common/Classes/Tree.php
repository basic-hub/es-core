<?php

namespace BasicHub\EsCore\Common\Classes;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\Spl\SplBean;

class Tree extends SplBean
{
    /**
     * 初始数据，执行完initialize之后就无了
     * @var array
     */
    protected $data = [];

    /**
     * 根元素id
     * @var int
     */
    protected $rootId = 0;

    /**
     * 可用id的列表, null-不限制
     * @var null | array | int
     */
    protected $filterIds = null;

    /**
     * 客户端路由格式
     * @var bool
     */
    protected $isRouter = false;

    protected $idKey = 'id';
    protected $pidKey = 'pid';
    protected $childKey = 'children';

    /**
     * 树形数据
     * @var array
     */
    private $tree = [];

    private $origin = [];
    private $parent = [];
    private $children = [];

    protected function initialize(): void
    {
        foreach ($this->data as $value) {
            if ($value instanceof AbstractModel) {
                $value = $value->toArray();
            }
            $this->setNode($value[$this->idKey], $value[$this->pidKey], $value);
        }
        unset($this->data);

        $this->filters();
        $this->filterByRoot();
        $this->toRouter();
    }

    protected function setNode($id, $pid = 0, $data = [])
    {
        $this->origin[$id] = $data;
        $this->children[$pid][$id] = $id;
        $this->parent[$id] = $pid;
    }

    /**
     * 所有祖先元素的id
     * @param array $ids
     * @return array 一维
     */
    protected function getParents(array $ids = [])
    {
        $result = [];
        $queue = $ids;
        while ($queue) {
            $id = array_shift($queue);
            if (isset($result[$id])) {
                continue;
            }
            $result[$id] = $id;
            if (isset($this->parent[$id])) {
                $queue[] = $this->parent[$id];
            }
        }
        return array_values($result);
    }

    /**
     * 根据filterIds过滤某些数据
     * @return void
     */
    protected function filters()
    {
        if (is_null($this->filterIds)) {
            return;
        }
        if (is_numeric($this->filterIds)) {
            $this->filterIds = [$this->filterIds];
        }
        $allow = array_flip($this->getParents($this->filterIds));
        foreach ($this->origin as $key => $value) {
            if ( ! isset($allow[$key])) {
                unset($this->origin[$key]);
            }
        }
    }

    /**
     * 根据rootId裁剪树，只保留rootId子树内的节点
     * @return void
     */
    protected function filterByRoot()
    {
        if ($this->rootId == 0 || ! isset($this->children[$this->rootId])) {
            return;
        }
        $keep = [];
        $queue = array_values($this->children[$this->rootId]);
        while ($queue) {
            $id = array_shift($queue);
            $keep[$id] = true;
            if (isset($this->children[$id])) {
                foreach ($this->children[$id] as $cid) {
                    $queue[] = $cid;
                }
            }
        }
        foreach ($this->origin as $key => $value) {
            if ( ! isset($keep[$key])) {
                unset($this->origin[$key]);
            }
        }
    }

    /**
     * 生成完整树形结构
     * @return array
     */
    public function treeData()
    {
        // 拓扑排序：保证父节点先于子节点处理
        $inDegree = array_fill_keys(array_keys($this->origin), 0);
        foreach ($this->origin as $id => $value) {
            $pid = $value[$this->pidKey];
            if ($pid != $this->rootId && isset($inDegree[$pid])) {
                $inDegree[$id]++;
            }
        }
        $queue = array_keys(array_filter($inDegree, fn($d) => $d === 0));
        $sorted = [];
        while ($queue) {
            $id = array_shift($queue);
            $sorted[] = $id;
            foreach ($this->children[$id] ?? [] as $cid) {
                if (isset($inDegree[$cid]) && --$inDegree[$cid] === 0) {
                    $queue[] = $cid;
                }
            }
        }

        $tree = $this->origin;
        foreach ($sorted as $id) {
            $pid = $tree[$id][$this->pidKey];
            if ($pid == $this->rootId) {
                $this->tree[] = &$tree[$id];
                continue;
            }
            $len = count($tree[$pid][$this->childKey] ?? []);
            $tree[$pid][$this->childKey][$len] = $tree[$id];
            $tree[$id] = &$tree[$pid][$this->childKey][$len];
        }

        return $this->tree;
    }

    /****************** 以下为vue-router相关方法 *******************/

    /**
     * 将源数据转换为vue-router格式
     * @return void
     * @throws \EasySwoole\Validate\Exception\Runtime
     */
    protected function toRouter()
    {
        if ( ! $this->isRouter) {
            return;
        }
        $validate = new \EasySwoole\Validate\Validate();
        $validate->addColumn('path')->url();
        $validate->addColumn('isext')->differentWithColumn(1);
        foreach ($this->origin as &$value) {
            // 构造树形结构必须的几个key
            $row = [
                $this->idKey => $value[$this->idKey],
                $this->pidKey => $value[$this->pidKey],
            ];
            foreach (['path', 'component', 'name', 'redirect',] as $col) {
                $row[$col] = $value[$col] ?? '';
            }

            // meta,强类型,对应types/vue-router.d.ts
            $meta = [
                'orderNo' => intval($value['sort']),
                'title' => $value['title'],
                'ignoreKeepAlive' => $value['keepalive'] != 1,
                'affix' => $value['affix'] == 1,
                'icon' => $value['icon'],
                'hideMenu' => $value['isshow'] != 1,
                'hideBreadcrumb' => $value['breadcrumb'] != 1
            ];
            // 外部链接, isext=1为外链，=0为frameSrc
            $isFrame = $validate->validate($value);
            if ($isFrame) {
                $meta['frameSrc'] = $value['path'];
                // 当为内嵌时，path已经不需要了，但优先级比frameSrc高，需要覆盖掉path为非url
                $row['path'] = $value['name'] ?? '';
            }
            $row['meta'] = $meta;
            $value = $row;
        }
    }

    /**
     * 获取某一个菜单的完整path，对应vben的homePath字段
     * @param array|int|null $id 菜单id
     * @param string $column
     * @return string
     */
    public function getHomePage($id = null, $column = 'path')
    {
        // 不传则使用filterIds
        if (is_null($id)) {
            $id = $this->filterIds;
        }
        $id = is_array($id) ? $id[0] : $id;
        $path = $this->getFullPath($id, $column);
        return implode('/', array_reverse($path));
    }

    /**
     * 指定id的完整path路径，有序
     * @param int $id
     * @param string $column
     * @return array
     */
    protected function getFullPath($id, $column = '')
    {
        $path = [];
        $current = $id;
        while (isset($this->origin[$current][$column])) {
            $path[] = $this->origin[$current][$column];
            $current = $this->parent[$current] ?? null;
            if ($current === null) {
                break;
            }
        }
        return $path;
    }
}
