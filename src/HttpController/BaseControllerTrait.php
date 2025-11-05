<?php

namespace BasicHub\EsCore\HttpController;

use BasicHub\EsCore\Common\Classes\LamJwt;
use BasicHub\EsCore\Common\Classes\LamOpenssl;
use BasicHub\EsCore\Common\Exception\JwtException;
use BasicHub\EsCore\HttpTracker\Index as HttpTracker;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Http\Message\Status;
use EasySwoole\Redis\Redis;
use BasicHub\EsCore\Common\Exception\HttpParamException;
use BasicHub\EsCore\Common\Exception\WarnException;
use BasicHub\EsCore\Common\Http\Code;
use BasicHub\EsCore\Common\Languages\Dictionary;

/**
 * 此类应该由最父级控制器use，控制器结构应该为  BaseController(此类use) > xxx(Admin|Sdk|Pay|Log)/BaseController(业务Base类) > 各业务类
 * @mixin Controller
 */
trait BaseControllerTrait
{
    /**
     * get参数，包含rsa解密参数
     * @var array
     */
    protected $get = [];

    /**
     * post参数，包含rsa解密参数
     * @var array
     */
    protected $post = [];

    /**
     * get+post参数，包含rsa解密参数
     * @var array
     */
    protected $input = [];

    /**
     * 仅通过rsa解密得到的参数，如果业务层有关键参数一定需要通过rsa拿到，则应该使用此属性
     * @var array
     */
    protected $rsa = [];

    /**
     * @var mixed rawContent
     */
    protected $raw = '';

    private $langsConstants = [];

    protected $actionNotFoundPrefix = '_';

    /**
     * http_tracker相关配置，由子类重写
     * @var array
     */
    protected $httpTracker = [
        // 是否开启，默认不开启
        'open' => false,
        // redis队列名称
        'queue_name' => 'Report:Origin-HttpTracker',
        // redis连接名
        'pool_name' => 'log',
        // 不记录链路的uri path
        'ignore_path' => [],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->setLanguageConstants();
    }

    protected function onRequest(?string $action): ?bool
    {
        // 请求参数rsa解密
        $this->decodeRsa();

        // 请求参数二次处理
        $this->requestParams();

        // 记录http_tracker日志
        $this->httpTrackerStart();

        return parent::onRequest($action);
    }

    protected function afterAction(?string $actionName): void
    {
        $this->httpTrackerEnd();
    }

    protected function requestParams()
    {
        $this->get = $this->request()->getQueryParams();

        $post = $this->request()->getParsedBody();
        if (empty($post)) {
            $post = $this->json();
        }
        $this->post = is_array($post) ? $post : [];
        $this->input = array_merge($this->get, $this->post);

        //  $this->request()->getSwooleRequest()->rawContent()也可以
        $this->raw = $this->request()->getBody()->__toString();

        $rsa = is_array($this->rsa) ? $this->rsa : [];
        $extend = $rsa + $this->requestParamsExtend();
        foreach ($extend as $key => $value) {
            foreach (['get', 'post', 'input'] as $proName) {
                if (empty($this->$proName[$key])) {
                    $this->$proName[$key] = $value;
                }
            }
        }
    }

    // 写入额外参数至get|post|input
    protected function requestParamsExtend()
    {
        return ['ip' => ip($this->request())];
    }

    protected function decodeRsa()
    {
        $cipher = $this->request()->getRequestParam(config('RSA.key'));
        // 私钥解密
        $envkeydata = LamOpenssl::getInstance()->privateDecrypt($cipher);

        // 尝试json结构转化，除了json，就是queryString格式
        if ($json = json_decode($envkeydata, true)) {
            $struct = $json;
        } else {
            parse_str($envkeydata, $struct);
        }

        $this->rsa = $struct ?: [];
    }

    /**
     * 校验jwt token
     * @param string $chkdata 检查参数key，空值则不进行检查
     * @return void
     */
    protected function checkJwtToken($chkKey = '')
    {
        $jwtCfg = config('ENCRYPT');
        $token = $this->request()->getHeader($jwtCfg['jwtkey'])[0] ?? '';
        if (empty($token)) {
            throw new HttpParamException('Jwt token is Empty.', Code::CODE_BAD_REQUEST);
        }

        // 验证JWT
        $jwt = LamJwt::verifyToken($token, $jwtCfg['key']);

        if ($chkKey) {
            if (empty($jwt[$chkKey])) {
                throw new HttpParamException('jwt Error', Code::CODE_UNAUTHORIZED);
            }

            // input中无此参数，或与jwt解密参数不符
            if (empty($this->input[$chkKey]) || $this->input[$chkKey] != $jwt[$chkKey]) {
                throw new HttpParamException("jwt的 $chkKey 不符:" . ($jwt[$chkKey] ?? ''), Code::CODE_PRECONDITION_FAILED);
            }
        }
    }

    protected function httpTrackerStart()
    {
        if (empty($this->httpTracker['open'])) {
            return;
        }
        $request = $this->request();
        if (is_array($this->httpTracker['ignore_path']) && in_array($request->getUri()->getPath(), $this->httpTracker['ignore_path'])) {
            return;
        }

        // SaveHandler配置
        $handleConfig = [
            'queue' => $this->httpTracker['queue_name'],
            'redis-name' => $this->httpTracker['pool_name'],
        ];
        // 根节点名称
        $rootName = get_mode('all');
        $point = HttpTracker::getInstance($handleConfig)->createStart($rootName);

        $_body = $request->getBody()->__toString() ?: $request->getSwooleRequest()->rawContent();

        // 如果希望查询某一个key，又不确定在GET还是POST还是XML中，此时查起来会很麻烦，是否需要新增一个ALL 将所有参数合并集中到一个key来进行查询 ??
        $effect = [
            'GET' => $request->getQueryParams(),
            'POST' => $request->getParsedBody(),
            'JSON' => json_decode($_body, true),
        ];

        $isXml = stripos($request->getHeaderLine('content-type'), 'xml') !== false;
        if ($isXml) {
            libxml_use_internal_errors(true);
            $effect['XML'] = json_decode(json_encode(simplexml_load_string($_body, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        }

        // 去除空值
        $effect = array_filter($effect);

        $params = [
            'url' => $request->getUri()->__toString(),
            'ip' => ip($request),
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'server_name' => config('SERVNAME'),
            'header' => $request->getHeaders(),
//            'server' => $request->getServerParams(),
            'repeated' => intval(stripos($request->getHeaderLine('user-agent'), ';HttpTracker') !== false)
        ] + $effect;

        $point && $point->setStartArg($params);
    }

    protected function httpTrackerEnd()
    {
        if (empty($this->httpTracker['open'])) {
            return;
        }
        $request = $this->request();
        if (is_array($this->httpTracker['ignore_path']) && in_array($request->getUri()->getPath(), $this->httpTracker['ignore_path'])) {
            return;
        }

        $response = $this->response();
        $data = $response->getBody()->__toString();
        if (is_string($data) && ($array = json_decode($data, true))) {
            $data = $array;
        }
        $code = $response->getStatusCode();

        // 302重定向，则记录Location地址
        if ($code === Status::CODE_MOVED_TEMPORARILY) {
            $data = $response->getHeader('Location');
        }
        $endData = ['httpStatusCode' => $code, 'data' => $data];

        $point = HttpTracker::getInstance()->startPoint();
        $point && $point->setEndArg($endData)->end();
    }

    protected function setLanguageConstants()
    {
        $dictionary = config('CLASS_DICTIONARY');
        if ( ! $dictionary || ! class_exists($dictionary)) {
            $appLanguage = '\\App\\Common\\Languages\\Dictionary';
            $dictionary = class_exists($appLanguage) ? $appLanguage : Dictionary::class;
        }
        $objClass = new \ReflectionClass($dictionary);
        $this->langsConstants = $objClass->getConstants();
    }

    protected function getLanguageConstants()
    {
        return $this->langsConstants;
    }

    protected function onException(\Throwable $throwable): void
    {
        if ($throwable instanceof HttpParamException || $throwable instanceof JwtException) {
            $message = $throwable->getMessage();
        } elseif ($throwable instanceof WarnException) {
            $message = $throwable->getMessage();
            $task = \EasySwoole\EasySwoole\Task\TaskManager::getInstance();
            $task->async(new \BasicHub\EsCore\Task\Error(
                    [
                        'message' => $message,
                        'file' => $throwable->getFile(),
                        'line' => $throwable->getLine(),
                    ], $throwable->getData())
            );
        } else {
            $message = ! is_env('produce') ? $throwable->getMessage() : lang(Dictionary::BASECONTROLLERTRAIT_1);
            // 交给异常处理器
            \EasySwoole\EasySwoole\Trigger::getInstance()->throwable($throwable);
        }
        $this->error($throwable->getCode() ?: Code::CODE_INTERNAL_SERVER_ERROR, $message);
    }

    protected function success($result = null, $msg = null)
    {
        return $this->writeJson(Code::CODE_OK, $result, $msg);
    }

    protected function error(int $code, $msg = null, $result = [])
    {
        $this->writeJson($code, $result, $msg);
        return false;
    }

    protected function writeJson($statusCode = 200, $result = null, $msg = null)
    {
        if ( ! $this->response()->isEndResponse()) {

            if (is_null($msg)) {
                $msg = Code::getReasonPhrase($statusCode);
            } elseif ($msg && in_array($msg, $this->langsConstants)) {
                $msg = lang($msg);
            }

            $data = [
                'code' => $statusCode,
                'result' => $result,
                'msg' => $msg ?? ''
            ];
            $this->response()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
            // 浏览器对axios隐藏了http错误码和异常信息，如果程序出错，通过业务状态码告诉客户端
            $this->response()->withStatus(Code::CODE_OK);
            return true;
        } else {
            return false;
        }
    }

    protected function writeUpload($url, $code = 200, $msg = '')
    {
        if ( ! $this->response()->isEndResponse()) {

            $data = [
                'code' => $code,
                'url' => $url,
                'msg' => $msg
            ];
            $this->response()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
            $this->response()->withStatus(Code::CODE_OK);
            return true;
        } else {
            return false;
        }
    }

    protected function isMethod($method)
    {
        return strtoupper($this->request()->getMethod()) === strtoupper($method);
    }

    protected function isHttpGet()
    {
        return $this->isMethod('GET');
    }

    protected function isHttpPost()
    {
        return $this->isMethod('POST');
    }

    // 兼容多种客户端
    protected function isHttpAjax()
    {
        return $this->request()->getHeaderLine('x-requested-with') === 'XMLHttpRequest';
    }

    protected function getStaticClassName()
    {
        $array = explode('\\', static::class);
        return end($array);
    }

    protected function actionNotFoundName()
    {
        return $this->actionNotFoundPrefix . $this->getActionName();
    }

    /**
     * 去除了公共前缀的 $this->getAllowMethodReflections() key列表
     * @param null $call
     * @return array|false[]|int[]|string[]
     */
    protected function getAllowMethods($call = null)
    {
        return array_map(
            function ($val) use ($call) {
                if (strpos($val, $this->actionNotFoundPrefix) === 0) {
                    $val = substr($val, strlen($this->actionNotFoundPrefix));
                }
                return (is_callable($call) || (is_string($call) && function_exists($call))) ? $call($val) : $val;
            },
            array_keys($this->getAllowMethodReflections())
        );
    }

    /**
     * @param string|null $action
     */
    protected function actionNotFound(?string $action)
    {
        $actionName = $this->actionNotFoundName();
        // 仅调用public，避免与普通方法混淆
        $publics = $this->getAllowMethodReflections();

        if (isset($publics[$actionName])) {
            $this->$actionName();
        } else {
            parent::actionNotFound($action);
        }
    }

    /**
     * 接口限流，redis计数
     * @param Redis $redis
     * @param string $cfgKey
     * @param array $input
     * @param bool $isWhite 是否白名单，白名单不受限制
     * @return \Closure
     * @throws HttpParamException
     */
    protected function requestLimit(Redis $redis, string $cfgKey, $input = [], $isWhite = false)
    {
        // 配置参考
        /*'REQUEST_LIMIT' => [
            'user_reg' => [
                'ip' => [
                    'interval' => 86400,
                    'times' => 5,
                    'limit_msg' => '此ip注册数已达上限',
                    'limit_code' => 422
                ],
                'devid' => [
                    'interval' => 86400,
                    'times' => 3,
                    'limit_msg' => '此设备注册数已达上限',
                    'limit_code' => 423
                ],
            ],
        ];*/
        $input = $input ?: $this->input;
        $config = config("REQUEST_LIMIT.$cfgKey");
        if ($config) {
            foreach ($config as $lk => $lv) {
                if (!isset($input[$lk])) {
                    continue;
                }
                $lkey = "request_limit_{$cfgKey}_{$lk}_$input[$lk]";
                if ($redis->exists($lkey)) {
                    // 请求开始时还未自增
                    if (!$isWhite && $redis->get($lkey) >= $lv['times']) {
                        throw new HttpParamException($lv['limit_msg'], $lv['limit_code']);
                    }
                } else {
                    $redis->setEx($lkey, $lv['interval'], 0);
                }
            }
        }

        // 计数自增
        return function () use ($config, $input, $redis, $cfgKey) {
            if ($config) {
                foreach ($config as $lk => $lv) {
                    if (isset($input[$lk])) {
                        $key = "request_limit_{$cfgKey}_{$lk}_$input[$lk]";
                        // 防止时间设置过短，key已过期则不理，下次重新开始计数
                        if ($redis->exists($key)) {
                            $redis->incr($key);
                        }
                    }
                }
            }
        };
    }

    /**
     * Redis分布式锁，处理多台机器批量收到相同请求的问题，主要处理异常请求，不设白
     * @param Redis $redis
     * @param string $cfgKey
     * @param array $input
     * @return void
     * @throws HttpParamException
     */
    protected function requestLock(Redis $redis, string $cfgKey, array $input = [])
    {
        // 配置参考
        /*'REQUEST_LOCK' => [
            'create_order' => [
                'uid' => [
                    'required' => true,
                    'interval' => 3,
                    'limit_msg' => '操作过于频繁，请稍后再试',
                    'limit_code' => 422,
                ],
            ],
        ],*/
        $input = $input ?: $this->input;
        $config = config("REQUEST_LOCK.$cfgKey");
        if (empty($config)) {
            return;
        }

        $time = time();
        foreach ($config as $lk => $lv) {
            if (!isset($input[$lk]) || $input[$lk] === '') {
                if ($lv['required']) {
                    // 必传
                    throw new HttpParamException(lang(Dictionary::PARAMS_ERROR), $lv['limit_code']);
                } else {
                    // 可选
                    continue;
                }
            }
            $lkey = "request_lock_{$cfgKey}_{$lk}_$input[$lk]";

            // PS：有玩家key一直没被删除，增加时间校验机制，超时则删key重新抢锁
            $last = $redis->get($lkey);
            if ($last && is_numeric($last) && $time - $last > $lv['interval']) {
                $redis->del($lkey);
            }

            $isLock = $redis->setNx($lkey, $time);
            if (!$isLock) {
                throw new HttpParamException($lv['limit_msg'], $lv['limit_code']);
            }
            // 设置有效期
            $redis->expire($lkey, $lv['interval']);
        }
    }
}
