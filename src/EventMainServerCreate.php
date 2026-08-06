<?php

namespace BasicHub\EsCore;

use BasicHub\EsCore\Consumer\Config as ConsumerConfig;
use BasicHub\EsCore\Template\RenderEngine;
use EasySwoole\Command\Color;
use EasySwoole\EasySwoole\Command\Utility;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Socket\AbstractInterface\ParserInterface;
use EasySwoole\Spl\SplBean;
use EasySwoole\Template\Render;
use EasySwoole\Utility\Str;
use EasySwoole\WordsMatch\WMServer;
use BasicHub\EsCore\Notify\EsNotify;

class EventMainServerCreate extends SplBean
{
    /**
     * 必传，MainServerCreate EventRegister对象
     * @var null | EventRegister
     */
    protected $EventRegister = null;

    /**
     * WebSocket事件， [EventRegister::onOpen => [Events::class, 'onOpen']]
     * @var null
     */
    protected $webSocketEvents = null;

    /**
     * WebSocket解释器
     * @var null
     */
    protected $WebSocketParser = WebSocket\Parser::class;

    /**
     *
     * @var null
     */
    protected $crontabClass = Crontab\Crontab::class;
    protected $crontabRunEnv = ['dev', 'produce'];


    protected $hotReloadWatchDirs = [EASYSWOOLE_ROOT . '/App', EASYSWOOLE_ROOT . '/vendor/wonder-game'];
    protected $hotReloadFunc = [
        'on_change' => null, // callback Change事件
        'on_exception' => null, // callback 异常
        'reload_before' => null, // callback worker process reload 前
        'reload_after' => null, // callback worker process reload 后
    ];

    protected $consumerJobs = null;

    protected function initialize(): void
    {

    }

    public function run()
    {
        // 仅在开启的是WebSocket服务时
        if (config('MAIN_SERVER.SERVER_TYPE') === EASYSWOOLE_WEB_SOCKET_SERVER) {
            $this->registerWebSocketServer();
        }
        $this->registerCrontab();
        $this->registerConsumer();
        $this->watchHotReload();
        $this->registerNotify();
        $this->wordsMatch();
        $this->tplEngine();
    }

    protected function registerWebSocketServer()
    {
        $register = $this->EventRegister;
        if ( ! $register instanceof EventRegister) {
            throw new \Exception('EventRegister Error');
        }

        $config = new \EasySwoole\Socket\Config();
        $config->setType(\EasySwoole\Socket\Config::WEB_SOCKET);
        if ($this->WebSocketParser) {
            $parserClassName = $this->WebSocketParser;
            $ParserClass = new $parserClassName();
            if ($ParserClass instanceof ParserInterface) {
                $config->setParser($ParserClass);
            }
        }

        $dispatch = new \EasySwoole\Socket\Dispatcher($config);
        $register->set(
            $register::onMessage,
            function (\Swoole\Websocket\Server $server, \Swoole\WebSocket\Frame $frame) use ($dispatch) {
                $dispatch->dispatch($server, $frame->data, $frame);
            }
        );
        $events = $this->webSocketEvents;
        if (is_array($events)) {
            foreach ($events as $event => $item) {
                $register->add($event, $item);
            }
        } else if (is_string($events) && class_exists($events)) {
            $allowNames = (new \ReflectionClass(EventRegister::class))->getConstants();
            $Ref = new \ReflectionClass($events);
            $public = $Ref->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($public as $item) {
                $name = $item->name;
                if ($item->isStatic() && isset($allowNames[$name])) {
                    $register->add($allowNames[$name], [$item->class, $name]);
                }
            }
        }
    }

    /**
     * 注册Crontab
     * @return void
     */
    protected function registerCrontab()
    {
        if (is_array($this->crontabRunEnv) && class_exists($this->crontabClass) && is_env($this->crontabRunEnv)) {
            $Crontab = \EasySwoole\EasySwoole\Crontab\Crontab::getInstance();
            $Crontab->addTask($this->crontabClass);
        }
    }

    /**
     * php easyswoole process show -d -mode=xx.xx.xx
     * 注册自定义进程
     * @return void
     */
    protected function registerConsumer()
    {
        $jobs = $this->consumerJobs;
        if (!is_array($jobs)) {
            return;
        }

        foreach ($jobs as $config) {
            if ( ! $config instanceof ConsumerConfig) {
                throw new \Exception('consumerJobs Items not instanceof ConsumerConfig');
            }

            $className = $config->getClassName();
            if (empty($className) || ! class_exists($className)) {
                throw new \Exception('consumerJobs Items ClassName not found');
            }

            $server = $config->getServerNumber();
            if ($server && defined('SERVNUM') && ! in_array(SERVNUM, $server)) {
                continue;
            }

            // 进程分组
            $group = config('SERVER_NAME') . '.my';

            $proName = $config->getProName();
            $proNum = $config->getProNum();
            $swProConfig = $config->getSwProConfig();

            for ($i = 0; $i < $proNum; ++$i) {
                $cfg = array_merge([
                    'processName' => "$group.$proName.$i",
                    'processGroup' => $group,
                    'arg' => $config,
                    'enableCoroutine' => true,
                ], $swProConfig);
                $processConfig = new \EasySwoole\Component\Process\Config($cfg);
                \EasySwoole\Component\Process\Manager::getInstance()->addProcess(new $className($processConfig));
            }
        }
    }

    protected function watchHotReload()
    {
        $watchConfig = (array)$this->hotReloadWatchDirs;

        // 本地开发环境可固定开启
        if ( ! is_env('dev')) {
            return;
        }

        $onChange = is_callable($this->hotReloadFunc['on_change'])
            ? $this->hotReloadFunc['on_change']
            : function (array $list, \EasySwoole\FileWatcher\WatchRule $rule) {
                echo PHP_EOL . PHP_EOL . Color::warning(' Worker进程重启，检测到以下文件变更: ') . PHP_EOL;

                foreach ($list as $item) {
                    $scanType = is_file($item) ? 'file' : (is_dir($item) ? 'dir' : '未知');
                    echo Utility::displayItem("[$scanType]", $item) . PHP_EOL;
                }
                $Server = ServerManager::getInstance()->getSwooleServer();

                // worker进程reload不会触发客户端的断线重连，但是原来的fd已经不可用了
                foreach ($Server->connections as $fd) {
                    // 不要在 close 之后写清理逻辑。应当放置到 onClose 回调中处理
                    $Server->close($fd);
                }

                if (is_callable($this->hotReloadFunc['reload_before'])) {
                    $this->hotReloadFunc['reload_before']($list, $rule);
                }

                $Server->reload();

                if (is_callable($this->hotReloadFunc['reload_after'])) {
                    $this->hotReloadFunc['reload_after']($list, $rule);
                }

                echo Color::success('Worker进程启动成功 ') . PHP_EOL;
                echo Color::red('请自行区分 Master 和 Worker 程序 !!!!!!!!!!') . PHP_EOL . PHP_EOL;
            };

        $onException = is_callable($this->hotReloadFunc['on_exception'])
            ? $this->hotReloadFunc['on_exception']
            : function (\Throwable $throwable) {

                echo PHP_EOL . Color::danger('Worker进程重启失败: ') . PHP_EOL;
                echo Utility::displayItem("[message]", $throwable->getMessage()) . PHP_EOL;
                echo Utility::displayItem("[file]", $throwable->getFile() . ', 第 ' . $throwable->getLine() . ' 行') . PHP_EOL;

                echo Color::warning('trace:') . PHP_EOL;
                if ($trace = $throwable->getTrace()) {
                    // 简单打印就行
                    var_dump($trace);
//                    foreach ($trace as $key => $item)
//                    {
//                        echo Utility::displayItem("$key-----------------------", $item) . PHP_EOL;
//                        foreach ($item as $ik => $iv)
//                        {
//                            echo Utility::displayItem("[$ik]", $iv) . PHP_EOL;
//                        }
//                        echo Utility::displayItem("$key-----------------------", $item) . PHP_EOL;
//                    }
                }
            };

        $watcher = new \EasySwoole\FileWatcher\FileWatcher();
        // 设置监控规则和监控目录
        foreach ($watchConfig as $dir) {
            if (is_dir($dir)) {
                $watcher->addRule(new \EasySwoole\FileWatcher\WatchRule($dir));
            }
        }

        $watcher->setOnChange($onChange);
        $watcher->setOnException($onException);
        $watcher->attachServer(ServerManager::getInstance()->getSwooleServer());
    }

    static public function registerNotify()
    {
        if ( ! is_array(config('ES_NOTIFY'))) {
            return;
        }
        foreach (config('ES_NOTIFY') as $key => $val) {
            if ( ! is_array($val)) {
                continue;
            }
            $className = '\\BasicHub\\EsCore\\Notify\\' . ucfirst($key) . '\\Config';

            foreach ($val as $k => $v) {
                if ( ! is_array($v)) {
                    continue;
                }
                // 类不存在，则在进程启动时就提示。要么不传，传了就要传对
                if (!class_exists($className)) {
                    throw new \Exception("Class Not found: $className");
                }
                EsNotify::getInstance()->register(new $className($v, true), $key, $k);
            }
        }
    }

    /**
     * 注册words-match服务
     * @document https://www.easyswoole.com/Components/WordsMatch/introduction.html
     * @return void
     */
    protected function wordsMatch()
    {
        if ( ! $cfg = config('WORDS_MATCH')) {
            return;
        }
        if ( ! is_array($cfg)) {
            return;
        }

        // 配置 words-match
        $wdConfig = new \EasySwoole\WordsMatch\Config();
        foreach ($cfg as $name => $val) {
            $method = 'set' . Str::studly($name);
            if (method_exists($wdConfig, $method)) {
                $wdConfig->$method($val);
            }
        }

        // 注册服务
        WMServer::getInstance($wdConfig)->attachServer(ServerManager::getInstance()->getSwooleServer());
    }

    /**
     * 注册模板进程
     * @param array $init
     * @param $serverNum
     * @return void
     */
    protected function tplEngine()
    {
        if ( ! $cfg = config('TPL_TEMPLATE')) {
            return;
        }
        $renderConfig = Render::getInstance()->getConfig();
        // 模板引擎，也可以使用 tp、smarty 等
        $engineName = (!empty($cfg['engine_class']) && class_exists($cfg['engine_class'])) ? $cfg['engine_class'] : RenderEngine::class;

        $renderConfig->setRender(new $engineName($cfg['init_params'] ?? []));
        $renderConfig->setTempDir($cfg['temp_dir'] ?? EASYSWOOLE_TEMP_DIR);
        $renderConfig->setTimeout($cfg['timeout'] ?? 5);
        $renderConfig->setServerName($cfg['server_name'] ?? config('SERVER_NAME'));
        $renderConfig->setWorkerNum($cfg['server_num'] ?? 2);
        Render::getInstance()->attachServer(ServerManager::getInstance()->getSwooleServer());
    }
}
