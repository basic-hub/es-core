<div align="center">

# 🚀 ES-Core

**基于 EasySwoole 的企业级开发框架核心组件**

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://www.php.net/)
[![EasySwoole](https://img.shields.io/badge/EasySwoole-%3E%3D3.4-green.svg)](http://www.easyswoole.com)
[![Swoole](https://img.shields.io/badge/Swoole-%3E%3D4.8-red.svg)](https://wiki.swoole.com)

[English](README_EN.md) | 简体中文

</div>

---

## 📖 简介

ES-Core 是一个基于 EasySwoole 封装的企业级开发框架核心组件库，提供了丰富的 Trait 和 Class 实现，旨在通过 Composer 实现多项目代码复用，提升开发效率。

### ✨ 核心特性

- 🎯 **模块化设计** - 灵活的 Trait 机制，不破坏 EasySwoole 推荐的继承关系
- 🔐 **安全加固** - 内置 RSA/AES 加密、JWT 认证、参数验证等安全机制
- 🌐 **多云支持** - 统一接口支持腾讯云、阿里云等多云服务商
- 📊 **链路追踪** - 完整的请求链路追踪和日志记录
- 🔄 **异步任务** - 支持自定义进程、定时任务、消息队列消费
- 🌍 **国际化** - 完善的 I18N 多语言支持
- 💾 **模型缓存** - 多种缓存策略（String/Hash/Set/SplArray）
- 📡 **WebSocket** - 完整的 WebSocket 连接管理和事件处理

---

## 📦 安装

```bash
composer require basic-hub/es-core
```

### 环境要求

| 依赖 | 版本要求 |
|------|---------|
| PHP | >= 7.4 |
| Swoole | >= 4.8 |
| EasySwoole | >= 3.4 |

### 相关资源

- [EasySwoole 官方文档](http://www.easyswoole.com)
- [Swoole 官方文档](https://wiki.swoole.com)
- [Composer 官网](https://getcomposer.org)

---

## 📂 目录结构

```
src/
├── Common/                           # 核心通用组件
│   ├── Classes/                      # 自定义工具类
│   │   ├── CtxManager               # 协程上下文管理器（增强版）
│   │   ├── DateUtils                # 时间日期时区处理工具
│   │   ├── ExceptionTrigger         # 异常上报处理器（Redis/HTTP）
│   │   ├── FdManager                # WebSocket 连接管理（Swoole\Table）
│   │   ├── Jwt                      # JWT 令牌处理
│   │   ├── Mysqli                   # MySQL 客户端封装
│   │   ├── TablePart                # 数据库分区自动管理
│   │   ├── Tree                     # 树形结构处理
│   │   └── XlsWriter                # Excel 导入导出
│   ├── CloudLib/                     # 多云服务商统一接口
│   │   └── 支持腾讯云、阿里云（可扩展）
│   ├── Exception/                    # 自定义异常类
│   ├── Http/                         # HTTP 相关配置
│   │   └── Code                     # HTTP 响应状态码（可继承扩展）
│   ├── Language/                     # 国际化 (I18N)
│   │   ├── Dictionary               # 国际化字典（可继承扩展）
│   │   └── Languages                # I18N 助手类
│   ├── Logs/                         # 自定义日志处理器
│   ├── Notify/                       # 多平台消息推送
│   │   ├── App                      # 应用内推送
│   │   ├── Template                 # 微信模板消息
│   │   ├── Webhook                  # Webhook 机器人
│   │   └── Message                  # 消息模板基类
│   ├── Geo/                          # IP 地理位置解析
│   ├── Openssl/                      # 加密解密（RSA/AES，可扩展）
│   └── OrmCache/                     # 模型缓存（String/Hash/Set/SplArray）
├── Consumer/                         # 自定义消费进程
│   └── 支持多任务同进程处理，Redis 队列消费
├── Crontab/                          # 定时任务系统
│   └── 支持动态开关、环境控制、参数传递
├── HttpController/                   # HTTP 控制器
│   ├── Admin/
│   │   ├── BaseTrait                # 基础控制器 Trait
│   │   └── AuthTrait                # CRUD 操作封装
│   ├── Api/
│   └── BaseController               # 所有控制器的基类
├── HttpTracker/                      # 链路追踪系统
│   ├── Index                        # 链路上下文管理
│   └── SaveHandler                  # 链路存储处理器
├── Model/                            # 数据模型层
│   └── BaseModelTrait               # 模型基类 Trait
├── Task/                             # 异步任务
│   └── Crontab                      # 通用异步任务模板
├── Template/                         # 简易模板引擎
├── WebSocket/                        # WebSocket 控制器
├── EventInitialize                  # EasySwoole 初始化事件封装
├── EventMainServerCreate            # EasySwoole 主服务创建事件封装
└── function.php                     # 通用函数库（可预定义覆盖）
```

---

## 🚦 快速开始

### Controller 示例

通过 Trait 快速构建管理后台控制器，内置 CRUD 操作：

```php
<?php
namespace App\HttpController\Admin;

use BasicHub\EsCore\HttpController\Admin\AuthTrait;

class UserController
{
    use AuthTrait;
    
    // AuthTrait 已提供以下方法：
    // - index()       列表查询（支持分页、搜索、排序）
    // - export()      导出（导出全部、异步导出）
    // - add()         新增数据
    // - edit()        编辑数据
    // - delete()      删除数据
}
```

### Model 示例

快速构建数据模型，内置常用查询方法和缓存支持：

```php
<?php
namespace App\Model;

use BasicHub\EsCore\Model\BaseModelTrait;
use EasySwoole\ORM\AbstractModel;

class UserModel extends AbstractModel
{
    use BaseModelTrait;
    
    protected $tableName = 'users';
    
    // BaseModelTrait 已提供：
    // - 软删除支持
    // - 自动时间戳
    // - 模型缓存
    // - 批量操作
    // - 关联查询等
}
```

### JWT 认证示例

```php
<?php
use BasicHub\EsCore\Common\Classes\Jwt;

// 生成 Token
$jwt = new Jwt($secretKey);
$token = $jwt->encode([
    'user_id' => 1001,
    'username' => 'john',
    'exp' => time() + 7200
]);

// 验证 Token
$payload = $jwt->decode($token);
if ($payload) {
    echo "User ID: " . $payload['user_id'];
}
```

---

## 🔧 常用命令

### 进程管理

```bash
# 查看进程信息
php easyswoole process show -d

# 杀死指定进程
php easyswoole process kill --pid=PID

# 杀死指定进程组
php easyswoole process kill --group=GROUP_NAME

# 杀死所有进程
php easyswoole process killAll

# 强制杀死进程（带 -f 参数）
php easyswoole process kill --pid=PID -f
```

### Task 任务管理

```bash
# 查看 Task 进程状态
php easyswoole task status
```

---

## ❓ 常见问题

<details>
<summary><b>为什么 function.php 不写在此项目的 composer.json？</b></summary>

`function.php` 应该由业务项目的 `composer.json` 定义引入顺序，位置必须在项目自定义函数之后，否则无法预定义函数进行覆盖。如果放在此项目的 `composer.json` 中会被优先加载，失去灵活性。

</details>

<details>
<summary><b>为什么多数文件选择 Trait 而不使用继承？</b></summary>

Trait 和继承各有优劣，选择 Trait 的主要目的是**不破坏 EasySwoole 推荐的继承关系**，保持框架的灵活性和扩展性。

</details>

<details>
<summary><b>使用 Trait 有哪些注意事项？</b></summary>

1. **不允许重写属性** - 定义了 `setTraitProtected` 方法来修改 Trait 属性
2. **不允许重载方法** - 需要及时调整代码封装，避免整体复制导致维护困难
3. **方法名前缀机制** - 控制器 public 方法默认添加固定前缀，通过 `BaseControllerTrait.php` 的 `actionNotFound` 方法实现灵活调用

</details>

<details>
<summary><b>CloudLib 依赖如何安装？</b></summary>

从最新版本开始，可选依赖改为在业务项目内按需引入，不再统一引入，大幅减小 vendor 体积。

请根据实际使用的云服务商到对应类声明中复制 `composer require` 命令安装所需依赖：
- 使用腾讯云：安装腾讯云 SDK
- 使用阿里云：安装阿里云 SDK
- 按需安装，避免冗余

</details>

---

## 🆕 版本更新说明

### 重要变更

#### 🔐 安全性增强
- **扩展参数独立存储** - 不再直接修改 EasySwoole 的 Request 对象，存储在控制器属性中，避免签名验证问题
- **RSA 密文解密独立** - 强制关键数据必须经过 RSA 解密，防止恶意攻击

#### 🏗️ 架构重构
- ✅ 重写链路追踪系统，支持子链路和异步任务追踪
- ✅ 重写 JWT Token 相关函数和类
- ✅ 重写协程上下文管理器，增强性能
- ✅ 重写 Openssl 加密解密，支持多种加密方式（可扩展）
- ✅ 重写 GeoIP 解析，支持多渠道（可扩展）
- ✅ 重构自定义消费进程，支持同进程处理多个任务

#### ⚡ 性能优化
- 移除所有 `eval` 动态解析，提升安全性和性能
- 优化 Redis 集群模式下分 key 规则及数据散列
- 增加客户端临时密钥直传对象存储，支持超大文件上传（理论无上限）

#### 🔌 扩展支持
- 模型相关调整，更符合 ORM 最佳实践
- CloudLib 增加多云商支持（腾讯云、阿里云）
- Notify 组件完全重写，支持多平台、多任务

#### 📦 依赖优化
- **可选依赖按需安装** - CloudLib 相关 Composer 依赖改为业务项目按需安装
- 大幅减小 vendor 体积，CI/CD 更快更轻量

### TODO 列表

- [x] 创建定时任务 Crontab 和消费任务 Consumer
- [x] 重写 ShardTable 类为 TablePart 类
- [x] WebSocket 相关类（事件、解析、Caller、连接管理）
- [x] Crontab 支持 database、file、http 等多种获取方式
- [x] es-orm-cache 组件封装
- [x] 重写 verify_token 为面向对象风格
- [x] CloudLib 依赖由业务层项目按需安装

---
## 📚 相关文档

- [Trait 冲突解决方案](https://www.php.net/manual/zh/language.oop5.traits.php)
- [XlsWriter 文档](https://xlswriter-docs.viest.me/zh-cn)

---

## 🤝 贡献

我们欢迎所有形式的贡献，无论是新功能、Bug 修复还是文档改进。

### 贡献指南

1. Fork 本仓库
2. 创建你的特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交你的修改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启一个 Pull Request

### 贡献者排行榜

<a href="https://openomy.com/basic-hub/es-core" target="_blank" style="display: block; width: 100%;margin-bottom: 1rem;" align="center">
  <img src="https://openomy.com/svg?repo=basic-hub/es-core&chart=bubble&latestMonth=12" target="_blank" alt="Contribution Leaderboard" style="display: block; width: 100%;" />
</a>

### 所有贡献者

<a href="https://github.com/basic-hub/es-core/graphs/contributors">
  <img alt="Contributors" src="https://contrib.rocks/image?repo=basic-hub/es-core" />
</a>

---

## ⭐ Star History

[![Star History Chart](https://api.star-history.com/svg?repos=basic-hub/es-core&type=Date)](https://star-history.com/#basic-hub/es-core&Date)

---

## 💖 鸣谢

感谢 [JetBrains](https://www.jetbrains.com/) 为开源项目提供免费的 IDE 授权

[<img src="https://resources.jetbrains.com/storage/products/company/brand/logos/jetbrains.png" alt="JetBrains Logo" height="100" />](https://www.jetbrains.com/)

---

## 📄 许可证

本项目采用 MIT 许可证。详情请参阅 [LICENSE](LICENSE) 文件。

---

## 📞 联系我们

- **Issues**: [GitHub Issues](https://github.com/basic-hub/es-core/issues)
- **Discussions**: [GitHub Discussions](https://github.com/basic-hub/es-core/discussions)

---

<div align="center">

**如果这个项目对你有帮助，请给我们一个 ⭐ Star！**

Made with ❤️ by Basic Hub Team

</div>
