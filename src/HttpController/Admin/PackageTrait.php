<?php

namespace BasicHub\EsCore\HttpController\Admin;

use BasicHub\EsCore\Common\Exception\HttpParamException;
use BasicHub\EsCore\Common\Languages\Dictionary;

/**
 * @mixin AuthTrait
 * @property \App\Model\Admin\Package $Model
 */
trait PackageTrait
{
    protected function __search()
    {
        $where = [];

        $filter = $this->filter();
        // 如果分配了包权限但没有分配该包所属的游戏权限，同样是看不到此包的
        foreach (['gameid', 'pkgbnd'] as $col) {
            empty($filter[$col]) or $where[$col] = [$filter[$col], 'IN'];
        }
        empty($this->get['name']) or $where['concat(name," ",pkgbnd)'] = ["%{$this->get['name']}%", 'like'];

        return $this->_search($where);
    }

    public function _saveKeyValue($return = false)
    {
        $model = $this->__getModel();
        if (empty($this->post['kv']) || empty($this->post['name'])) {
            throw new HttpParamException(lang(Dictionary::MISS_KEY_PARA));
        }

        // 支持无限极 a.b.c 语法
        $parts = explode('.', $this->post['name']);
        $node = $this->post['kv'];
        foreach (array_reverse($parts) as $part) {
            $node = [$part => $node];
        }

        $model->update(['extension' => $node]);
        return $return ? $model->toArray() : $this->success();
    }

    public function _options($return = false)
    {
        // 除了extension外的所有字段
        $options = $this->Model->order('p.gameid', 'desc')
            ->order('p.sort', 'asc')
            ->field(['p.id', 'p.name', 'p.gameid', 'p.pkgbnd', 'p.os', 'p.sort'])
            ->alias('p')
            ->where('g.status', 1)
            ->where('p.status', 1)
            ->join('game as g', 'p.gameid=g.id')
            ->all();
        return $return ? $options : $this->success($options);
    }
}
