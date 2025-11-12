<?php

namespace BasicHub\EsCore\Template;

use EasySwoole\Spl\SplBean;
use EasySwoole\Template\RenderInterface;

/**
 * 一个简单的模板引擎实现
 */
class RenderEngine extends SplBean implements RenderInterface
{
    /**
     * @var string
     */
    protected $fileSuffix = 'php';

    /**
     * @var string
     */
    protected $tpl404 = '';

    /**
     * 视图文件目录，默认\App\View下
     * @var string
     */
    protected $viewDir = '';

    public function render(string $template, ?array $data = null, ?array $options = null): ?string
    {
        $tplName = $this->getFileName($template);
        ob_start();
        extract($data);
        include $tplName;
        return ob_get_clean();
    }

    public function onException(\Throwable $throwable, $arg): string
    {
        return $throwable->getTraceAsString();
    }

    protected function getFileName($template)
    {
        $len = 0 - strlen($this->fileSuffix);
        if (substr($template, $len) !== $this->fileSuffix) {
            $template = rtrim($template, '.') . '.' . $this->fileSuffix;
        }

        $viewDir = $this->viewDir ?: (EASYSWOOLE_ROOT . '/App/View/');

        $tpl = $viewDir . ltrim($template, '/');
        if (is_file($tpl)) {
            return $tpl;
        }

        // 404
        $tpl404 = [
            $this->tpl404,
            $viewDir . $this->tpl404,
            EASYSWOOLE_ROOT . '/vendor/easyswoole/easyswoole/src/Resource/Http/404.html',
            EASYSWOOLE_ROOT . '/src/Resource/Http/404.html',
        ];

        foreach ($tpl404 as $_404) {
            if ( ! empty($_404) && is_file($_404)) {
                return $_404;
            }
        }
    }
}
