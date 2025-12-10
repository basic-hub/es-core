<?php

namespace BasicHub\EsCore\Common\CloudLib\Expense;

use EasySwoole\Spl\SplBean;
use EasySwoole\Utility\ArrayToTextTable;

abstract class Base extends SplBean implements ExpenseInterface
{
    protected $echo = true;

    /**
     * 将内容输出至控制台
     * @param array $header 表头，一维数组，示例: [name => 'Joyboo']
     * @param array $data 数据，二维数组
     * @return void
     */
    protected function echo($header, $data)
    {
        echo PHP_EOL;
        
        $Table = new ArrayToTextTable();

        $Table->isDisplayHeader(false);

        array_unshift($data, $header);

        echo $Table->getTable($data);
    }
}
