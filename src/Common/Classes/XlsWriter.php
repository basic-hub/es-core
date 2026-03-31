<?php

namespace BasicHub\EsCore\Common\Classes;

use EasySwoole\ORM\AbstractModel;
use Vtiful\Kernel\Excel;
use Vtiful\Kernel\Format;

/**
 * Create by Joyboo 2021-06-29
 *
 * XlsWriter官方文档：https://xlswriter-docs.viest.me/zh-cn/kuai-su-shang-shou/reader
 * 来自easyswoole官方的推荐 http://www.easyswoole.com/OpenSource/xlsWriter.html
 *
 * 支持游标模式的超大文件读取，内存消耗不到1MB，需安装XlsWriter.so扩展
 *
 * Class XlsWriter
 */
class XlsWriter
{
    const TYPE_INT = Excel::TYPE_INT;
    const TYPE_STRING = Excel::TYPE_STRING;
    const TYPE_DOUBLE = Excel::TYPE_DOUBLE;
    const TYPE_TIMESTAMP = Excel::TYPE_TIMESTAMP;

    protected $excel = null;

    protected $offset = 0;

    protected $setType = [];

    /**
     * 新版本的constMemory方法增加了第三个参数用来兼容WPS，在这之前，如果传递3个参数会报错
     * 自动生成属性，存储constMemory方法参数数量
     * @var int
     */
    protected $constMemoryArgc = 0;

    /** @var string 导出文件后缀，支持 .xlsx | .csv */
    protected $exportSuffix = '.xlsx';

    /**
     * @var array [
     *          'path' => 文件存放路径
     *          'thHeight' => 表头行高
     *      ]
     */
    protected $config = [
        'thHeight' => 15
    ];

    public function __construct($path = '')
    {
        if (empty($path)) {
            $path = config('export_dir');
        }
        $path = rtrim($path, '/') . '/';
        if ( ! is_dir($path)) {
            mkdir($path, 0777, true);
//            throw new \Exception('没有这个目录：' . $path);
        }

        $this->setConfig(['path' => $path]);
        $this->excel = new Excel($this->getConfig());
        $this->constMemoryArgc = count((new \ReflectionMethod($this->excel, 'constMemory'))->getParameters());
    }

    public function setConfig($config = [])
    {
        $this->config = array_merge_multi($this->config, $config);
    }

    public function getConfig($name = null)
    {
        if ( ! is_null($name)) {
            return $this->config[$name] ?? null;
        }
        return $this->config;
    }

    /**
     * 设置读取参数
     * @param int $offset 偏移量，传1会丢弃第一行，传2会丢弃第一行和第二行 ...
     * @param array $setType 列单元格数据类型，从0开始 [2 => \XlsWriter::TYPE_TIMESTAMP]表示第三列的单元格是时间类型
     * @return $this
     */
    public function setReader(int $offset = 0, array $setType = [])
    {
        $this->offset = $offset;
        $this->setType = $setType;
        return $this;
    }

    /**
     * 导入，游标模式
     * @param $file
     * @param callable $callback function(int $row, int $cell, $data)
     */
    public function readFileByCursor($file, callable $callback)
    {
        $sheetList = $this->excel->openFile($file)->sheetList();

        foreach ($sheetList as $sheetName) {
            $sheet = $this->excel->openSheet($sheetName);
            if ($this->offset > 0) {
                $sheet->setSkipRows($this->offset);
            }
            if ($this->setType) {
                $sheet->setType($this->setType);
            }
            $sheet->nextCellCallback($callback);
        }
    }

    /**
     * 导入，全量模式
     * @param $file
     */
    public function readFile($file)
    {
        $sheetList = $this->excel->openFile($file)->sheetList();

        $result = [];
        foreach ($sheetList as $sheetName) {
            $sheet = $this->excel->openSheet($sheetName);
            if ($this->offset > 0) {
                $sheet->setSkipRows($this->offset);
            }
            if ($this->setType) {
                $sheet->setType($this->setType);
            }
            $sheetData = $sheet->getSheetData();
            $result = array_merge($result, $sheetData);
            unset($sheetData, $sheet);
        }

        return $result;
    }

    /**
     * 导出，全量模式
     * @param $file
     * @param array $data
     * @param array $header
     */
    public function ouputFile($file, $data = [], $header = [])
    {
        $object = $this->excel->fileName($file);
        if ($header) {
            $object->header($header);
        }
        $object->data($data)->output();
    }

    /**
     * @param string $file
     * @param array $header ['uid' => '用户id', 'username' => '用户名', 'itime' => '时间']
     * @param AbstractModel|null $model
     * @param $chunk
     * @param callable|null $call
     * @return void
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function ouputFileByOrm(string $file, array $header, AbstractModel $model = null, $chunk = 1000, callable $call = null)
    {
        $suffix = '.xlsx';
        if (substr($file, -5) !== $suffix) {
            $file .= $suffix;
        }

        $this->excel->constMemory($file)->header(array_values($header));

        $this->setThStyle($this->excel);

        if ($model instanceof AbstractModel) {
            $model->field(array_keys($header))->chunk(function ($item) use ($header) {
                /** @var AbstractModel $item */
                $toArray = $item->toArray();
                $row = [];
                foreach ($header as $key => $value) {
                    // 按header顺序填充
                    $row[] = $toArray[$key] ?? '';
                }
                $this->excel->data([$row]);
            }, $chunk);
        } elseif (is_callable($call)) {
            $call($this->excel);
        }
        $this->excel->output();
    }

    /**
     * 导出，固定内存模式
     * @param string $file
     * @param array $header [
     *          // 普通key=val
     *          'reg' => '注册',
     *          // 设置类型,第二个参数为数据类型，暂时只对数字和字符串进行区分处理，客户端传值同Xlsx插件的ExcelDataType类型：b=Boolean, n=Number, e=error, s=String, d=Date, z=Stub
     *          'reg' => ['注册', 's']
     * ]
     * @param array | \Generator $data
     * @param callable | array | null $rowCall 多行处理回调，兼容__after_index
     */
    public function ouputFileByCursor(string $file, array $header, $data, $rowCall = null, $limit = 1000)
    {
        $isCsv = $this->exportSuffix === '.csv';
        $suffix = $isCsv ? '.csv' : '.xlsx';
        if (substr($file, -strlen($suffix)) !== $suffix) {
            $file .= $suffix;
        }

        $thKeys = $thValue = $thType = [];
        foreach ($header as $k => $v) {
            $thKeys[] = $k;
            if (is_string($v)) {
                $v = [$v];
            }
            $thValue[] = $v[0];
            $thType[$k] = $v[1] ?? '';
        }

        $cmy = $this->constMemoryArgc < 3 ? [$file] : [$file, null, false];
        $obj = $this->excel->constMemory(...$cmy);
        $excel = ($isCsv ? $obj : $this->setThStyle($obj))->header($thValue);

        $buildRows = function (array $outputs) use ($thKeys, $thType): array {
            $rows = [];
            foreach ($outputs as $value) {
                $row = [];
                foreach ($thKeys as $col) {

                    if (isset($value[$col])) {
                        $colval = $value[$col];
                    } elseif (strpos($col, '.') !== false) {
                        // 支持a.b.c语法
                        $colval = array_reduce(explode('.', $col), function ($carry, $key) {
                            return is_array($carry) || $carry instanceof AbstractModel ? ($carry[$key] ?? '') : '';
                        }, $value);
                    } else {
                        $colval = '';
                    }

                    // excel最多处理15位数字，超过此长度时，第16位及之后会被自动补0（例如输入911717296328700928会显示为911717296328700000）
                    $row[] = (isset($thType[$col]) && $thType[$col] === 'n' && strlen((string)$colval) <= 15)
                        ? floatval($colval)
                        : $colval;
                }
                if ($row) {
                    $rows[] = $row;
                }
            }
            return $rows;
        };

        $flush = function (array $batch) use ($excel, $rowCall, $buildRows) {
            if ($rowCall !== null) {
                $batch = call_user_func($rowCall, $batch);
            }
            $rows = $buildRows($batch);
            if ($rows) {
                $excel->data($rows);
            }
        };

        if (is_array($data)) {
            $flush($data);
        } elseif ($data instanceof \Generator) {
            $batch = [];
            foreach ($data as $item) {
                $batch[] = $item;
                if (count($batch) >= $limit) {
                    $flush($batch);
                    $batch = [];
                }
            }
            if ($batch) {
                $flush($batch);
            }
        }

        $excel->output();
    }

    /**
     * 创建excel模板
     * @param array $header
     * @param float $colWidth 列宽
     * @param float $rowHeight 行高
     * @return string
     */
    public function xlsxTemplate(array $header = [], $colWidth = 20)
    {
        $fileName = sprintf('export-%d-%s.xlsx', date(DateUtils::YmdHis), substr(uniqid(), -5));
        $header = array_values($header);

        // 如: header中有2个元素则是 A1:B1，3个元素则是 A1:C1 ...
        $len = count($header) - 1;
        $ascii = ord('A');
        $chr = chr($ascii + $len);

        $this->excel->fileName($fileName);

        $this->setThStyle($this->excel)
            ->setColumn("A1:{$chr}1", $colWidth)
            ->header($header)
            ->output();
        return $this->getConfig('path') . $fileName;
    }

    /**
     * 设置表头样式
     * @param Excel $excel
     * @return Excel
     */
    protected function setThStyle(Excel $excel)
    {
        $fileHandle = $excel->getHandle();
        $format = new Format($fileHandle);

        $formatHandle = $format
            // 加粗
            ->bold()
            // 水平居中 + 垂直居中
            ->align(Format::FORMAT_ALIGN_CENTER, Format::FORMAT_ALIGN_VERTICAL_CENTER)
            ->toResource();

        $excel->setRow('A1', $this->config['thHeight'], $formatHandle);
        return $excel;
    }

}

