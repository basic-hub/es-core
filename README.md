## 简介

    基于Easyswoole封装的一些Trait和Class，放到Composer仓库以实现多项目共用一套代码

## 开始

> composer require basic-hub/es-core

## 需要掌握的基础知识：

- [EasySwoole](http://www.easyswoole.com)
- [Swoole](https://wiki.swoole.com)
- [Composer](https://getcomposer.org)

## 目录结构及常用介绍

    src 理解为EasySwoole的App目录
     ├── Common  主要放一些非EasySwoole的东西
     |      ├── Classes 自定义类
     │      │     ├── CtxManager 二次封装的EasySwoole\Component\Context\ContextManager类，增加一些快捷方法
     │      │     ├── DateUtils 时间日期时区等处理
     │      │     ├── ExceptionTrigger 自定义异常处理器，将异常上报至redis或http
     │      │     ├── FdManager  WebSocket连接符管理，共享内存(Swoole\Table)实现
     │      │     ├── Jwt 
     │      │     ├── Mysqli 对MysqlClient的二次封装
     │      │     ├── TablePart 定时建分区、续分区
     │      │     ├── Tree 数行结构处理
     │      │     └── XlsWriter 数据导入和导出
     │      ├── CloudLib 云商各产品相关实现类，目前均已支持腾讯云、阿里云，可扩展
     │      ├── Exception 各种自定义异常
     │      ├── Http Http相关的配置
     │      │     └── Code Http响应状态码，项目的Code请`继承`它
     │      ├── Language I18N国际化目录
     │      │     ├── Dictionary 国际化字典，项目请`继承`它
     │      │     └── Languages I18n助手类，主要用来注册、设置
     │      │
     │      ├── Logs 自定义日志处理器
     │      ├── Geo IP解析相关
     │      ├── Openssl 加密解密处理，支持RSA、AES，可扩展
     │      └── OrmCache 模型缓存组件，已实现 String、Hash、Set、SplArray
     ├── Consumer  一些自定义进程实现，Base抽象类实现了基本Redis队列监听数据消费，但自定义进程能做的事情远不止如此，可自行发挥
     ├── Crontab  内置定时任务实现，可后台控制开关、运行环境、传递参数 等
     │
     ├── HttpController
     │        ├── Admin
     │        │     ├── BaseTrait 继承BaseController
     │        │     ├── AuthTrait 继承BaseTrait引用类，是其他控制器的父类，主要实现一些CURD等基础操作，子类可写最少代码实现相关功能
     │        │     └── ... 其他业务控制器
     │        ├── Api
     │        └── BaseController 所有控制器的基类
     ├── HttpTracker 链路追踪
     │        ├── Index 继承自PointContext，目的是为了默认开启autoSave及设置saveHandler，实例化时用它替代PointContext
     │        └── SaveHandler 实现SaveHandlerInterface接口
     ├── Model
     │     ├── BaseModelTrait 所有Model的基类
     │     └── ... 其他业务模型
     ├── Notify 通知类实现，内置飞书、钉钉、微信通知
     │      
     ├── Task 异步任务
     │     ├── Crontab 通用的异步任务模板
     │     └── ... 异步任务类
     ├── Template 简单的模板引擎实现，可选
     ├── WebSocket 同 HttpController
     ├── ... 其他业务
     ├── EventInitialize 对EasySwooleEvent::initialize事件的一些封装
     ├── EventMainServerCreate  对EasySwooleEvent::mainServerCreate事件的一些封装
     └── function.php 常用函数，项目可预定义对应函数以实现不同逻辑

Controller

```php
<?php
use BasicHub\EsCore\HttpController\Admin\AdminTrait;

class MyAdminController
{
	use AdminTrait;
    
	// here are some methods from AdminTrait ....
}

```

Model

```php
<?php
use BasicHub\EsCore\Model\AdminModelTrait;

class MyAdminModel
{
	use AdminModelTrait;
    
	// here are some methods from AdminModelTrait ....
}

```

## 答疑解惑

function.php 为何不写在此项目的composer.json

    function.php应该由项目的composer.json去定义引入的顺序
    位置一定得是在项目的函数引入之后，否则无法预定义函数，而放在此项目的composer.json会被优先加载

为何多数文件选择trait而不使用继承

    trait和继承各有优劣，选择trait目的是为了EasySwoole推荐的继承关系不被破坏

trait有哪些坑

    1. 不允许重写属性，所以基本都定义了一个setTraitProtected方法去修改trait属性
    2. 不允许重载方法，当某些项目可能比方法多一个小逻辑时，需要及时调整代码的封装，否则需要整个复制多一份，日积月累，反而可能更难维护
    3. 由于 2 的限制，现将普通控制器方法的public方法名默认添加一个固定前缀，通过基础控制器 /src/HttpController/BaseControllerTrait.php 的 actionNotFound 方法来实现更加灵活的调用方式


## 与老版本变化

- 扩展参数不再直接修改EasySwoole的Request对象，而是存储在控制器的属性中。
    
    改动原因：直接修改原Request会导致一些需要所有参数参与签名的地方无法处理

- rsa密文解密参数独立rsa属性，强制某些数据必须是经过rsa解密得到。

    改动原因：例如： envkeydata=xxxxxxxxx&name=Joyboo 。传递的envkeydata密文中是不包含 `name` 字段的，通过解密再合并到input中，此时 在业务层是无法区分name是不是由rsa解密的来的，只能区分有没有经过rsa解密和name字段有没有值，如果被外部恶意攻击关键字段，这里就是隐患。

- 重写链路日志相关实现
- 重写jwt token相关函数和类
- 重写协程上下文管理器
- 重写Openssl相关方法，支持多种加密方式，且可扩展
- 重写geoIP解析相关函数，支持多渠道，且可扩展
- 模型相关的调整
- 移除所有eval动态解析
- 增加客户端使用临时密钥直传对象存储方法，以支持超大文件上传，理论上无上限
- 优化Redis集群模式下分key规则及数据散列
- CloudLib增加火山云支持（待测）


## TODO

- [x] 创建定时任务Crontab和消费任务Consumer，src/Common/Classes/Crontab移动至src/Crontab目录
- [ ] 自定义Log处理器改为onLog + Event方式
- [ ] 重写Tree
- [x] 重写ShardTable类，已重写为TablePart类
- [x] WebSocket相关类，事件、解析、Caller、连接符管理等
- [x] Crontab支持database、file、http等方式获取
- [x] es-orm-cache 组件封装，替换原有的cacheinfo系列方法
- [ ] WebSocket实现导出全部，永不超时，进度实时可见，随时取消
- [ ] 定义模型Class映射
- [x] 重写verify_token为面向对象风格，验证与生成token解耦
- [ ] CloudLib相关Composer依赖由业务层项目安装，移除本仓库所有相关依赖

## 其他

- [trait冲突解决](https://www.php.net/manual/zh/language.oop5.traits.php)
- [XlsWriter](https://xlswriter-docs.viest.me/zh-cn)

## 感谢

[<img src="https://resources.jetbrains.com/storage/products/company/brand/logos/jetbrains.png" alt="JetBrains Logo (Main) logo." height="50" />](https://www.jetbrains.com/)

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=basic-hub/es-core&type=Date)](https://star-history.com/#basic-hub/es-core&Date)
