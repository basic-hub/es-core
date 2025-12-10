<?php

namespace BasicHub\EsCore\Common\CloudLib\Expense;

interface ExpenseInterface
{
    /**
     * 获取API原始数据
     * @return mixed
     */
    public function getExpense();

    /**
     * 处理后将内容输出为表格至控制台
     * @return mixed
     */
    public function echoTable();
}
