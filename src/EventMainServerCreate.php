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

        if (config('PROCESS_INFO.isopen')) {
            $this->EventRegister->add(EventRegister::onWorkerStart, [static::class, 'listenProcessInfo']);
        }
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
                return;
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
     * 侦听进程、协程、连接池信息
     * config结构： 'PROCESS_INFO' => [
     * 'isopen' => true,           // 是否开启
     * 'timer' => 5000,            // 定时器间隔时间
     * 'pool' => 'log',            // 写入redis连接池
     * 'queue' => 'ProcessInfo',   // 写入队列名
     * ]
     * @return void
     */
    public static function listenProcessInfo()
    {
        $cfg = config('PROCESS_INFO');

        if ($cfg && is_numeric($cfg['timer'])) {
            // 服务器id
            $servname = config('SERVNAME');
            // 当前服务
            $servername = config('SERVER_NAME');

            $mysql = config('MYSQL');
            $redis = config('REDIS');

            // jenkins 新旧程序切换,是否要延迟10s ?
            \EasySwoole\Component\Timer::getInstance()->loop($cfg['timer'], function () use ($servname, $servername, $mysql, $redis, $cfg) {
                $pid = getmypid();
                $info = [
                    'servname' => $servname,
                    'servername' => $servername,
                    'pid' => $pid,
                    'instime' => time(),
                ];
                // 进程信息
                $info['process'] = \EasySwoole\Component\Process\Manager::getInstance()->info($pid)[$pid];
                $info['name'] = $info['process']['name'];
                // 总协程信息
                $info['coroutine'] = \Swoole\Coroutine::stats();
                // 单个协程信息
                $coros = \Swoole\Coroutine::list();
                foreach ($coros as $cid) {
                    $info['coroutine_list'][$cid] = [
                        // 已运行时间，浮点毫秒
                        'runtime' => \Swoole\Coroutine::getelapsed($cid)
                    ];
                    // 调用堆栈
                    // \Swoole\Coroutine::getbacktrace($cid)
                }

                // mysql连接池
                foreach ($mysql as $dName => $dVal) {
                    $info['mysql_pool'][$dName] = [];
                    // status返回类型bug，遍历取当前进程
                    $dValues = DbManager::getInstance()->getConnection($dName)->__getClientPool()->status();
                    foreach ($dValues as $value) {
                        if ($value['pid'] === $pid) {
                            $info['mysql_pool'][$dName] = $value;
                        }
                    }
                }
                // redis连接池
                foreach ($redis as $rName => $rVal) {
                    $info['redis_pool'][$rName] = [];
                    $rValues = RedisPool::getInstance()->getPool($rName)->status();
                    foreach ($rValues as $value) {
                        if ($value['pid'] === $pid) {
                            $info['redis_pool'][$rName] = $value;
                        }
                    }
                }

                RedisPool::invoke(function (Redis $redis) use ($info, $cfg) {
                    $redis->rPush($cfg['queue'], json_encode($info, JSON_UNESCAPED_UNICODE));
                }, $cfg['pool'] ?? 'default');
            });
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
        // 配置 words-match
        $wdConfig = new \EasySwoole\WordsMatch\Config();
        $wdConfig->setDict($cfg['file']); // 配置 词库地址
        $wdConfig->setMaxMEM($cfg['mem'] ?? '512M'); // 配置 每个进程最大占用内存(M)，默认为 512 M
        $wdConfig->setTimeout($cfg['timeout'] ?? 3.0); // 配置 内容检测超时时间。默认为 3.0 s
        $wdConfig->setWorkerNum($cfg['num'] ?? 6); // 配置 进程数

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
