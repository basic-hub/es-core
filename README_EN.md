<div align="center">

# 🚀 ES-Core

**Enterprise-grade Development Framework Core Components Based on EasySwoole**

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://www.php.net/)
[![EasySwoole](https://img.shields.io/badge/EasySwoole-%3E%3D3.4-green.svg)](http://www.easyswoole.com)
[![Swoole](https://img.shields.io/badge/Swoole-%3E%3D4.8-red.svg)](https://wiki.swoole.com)

English | [简体中文](README.md)

</div>

---

## 📖 Introduction

ES-Core is an enterprise-grade development framework core component library based on EasySwoole, providing a rich set of Traits and Classes. It aims to achieve multi-project code reuse through Composer and improve development efficiency.

### ✨ Core Features

- 🎯 **Modular Design** - Flexible Trait mechanism that doesn't break EasySwoole's recommended inheritance relationships
- 🔐 **Security Hardening** - Built-in RSA/AES encryption, JWT authentication, parameter validation, and other security mechanisms
- 🌐 **Multi-Cloud Support** - Unified interface supporting Tencent Cloud, Alibaba Cloud, and other cloud service providers
- 📊 **Request Tracing** - Complete request chain tracing and logging
- 🔄 **Async Tasks** - Support for custom processes, scheduled tasks, and message queue consumption
- 🌍 **Internationalization** - Comprehensive I18N multi-language support
- 💾 **Model Caching** - Multiple caching strategies (String/Hash/Set/SplArray)
- 📡 **WebSocket** - Complete WebSocket connection management and event handling

---

## 📦 Installation

```bash
composer require basic-hub/es-core
```

### Requirements

| Dependency | Version |
|------------|---------|
| PHP | >= 7.4 |
| Swoole | >= 4.8 |
| EasySwoole | >= 3.4 |

### Related Resources

- [EasySwoole Official Documentation](http://www.easyswoole.com)
- [Swoole Official Documentation](https://wiki.swoole.com)
- [Composer Official Website](https://getcomposer.org)

---

## 📂 Directory Structure

```
src/
├── Common/                           # Core common components
│   ├── Classes/                      # Custom utility classes
│   │   ├── CtxManager               # Coroutine context manager (enhanced)
│   │   ├── DateUtils                # Date time timezone utilities
│   │   ├── ExceptionTrigger         # Exception reporting handler (Redis/HTTP)
│   │   ├── FdManager                # WebSocket connection manager (Swoole\Table)
│   │   ├── Jwt                      # JWT token handler
│   │   ├── Mysqli                   # MySQL client wrapper
│   │   ├── TablePart                # Database partition auto-management
│   │   ├── Tree                     # Tree structure handler
│   │   └── XlsWriter                # Excel import/export
│   ├── CloudLib/                     # Multi-cloud unified interface
│   │   └── Supports Tencent Cloud, Alibaba Cloud (extensible)
│   ├── Exception/                    # Custom exception classes
│   ├── Http/                         # HTTP related configurations
│   │   └── Code                     # HTTP response status codes (extendable)
│   ├── Language/                     # Internationalization (I18N)
│   │   ├── Dictionary               # I18N dictionary (extendable)
│   │   └── Languages                # I18N helper class
│   ├── Logs/                         # Custom log handlers
│   ├── Notify/                       # Multi-platform message push
│   │   ├── App                      # In-app push
│   │   ├── Template                 # WeChat template messages
│   │   ├── Webhook                  # Webhook bots
│   │   └── Message                  # Message template base class
│   ├── Geo/                          # IP geolocation parser
│   ├── Openssl/                      # Encryption/Decryption (RSA/AES, extensible)
│   └── OrmCache/                     # Model cache (String/Hash/Set/SplArray)
├── Consumer/                         # Custom consumer processes
│   └── Multi-task per process, Redis queue consumption
├── Crontab/                          # Scheduled task system
│   └── Dynamic switch, environment control, parameter passing
├── HttpController/                   # HTTP Controllers
│   ├── Admin/
│   │   ├── BaseTrait                # Base controller Trait
│   │   └── AuthTrait                # CRUD operations wrapper
│   ├── Api/
│   └── BaseController               # Base class for all controllers
├── HttpTracker/                      # Request tracing system
│   ├── Index                        # Trace context manager
│   └── SaveHandler                  # Trace storage handler
├── Model/                            # Data model layer
│   └── BaseModelTrait               # Model base Trait
├── Task/                             # Async tasks
│   └── Crontab                      # Common async task template
├── Template/                         # Simple template engine
├── WebSocket/                        # WebSocket controllers
├── EventInitialize                  # EasySwoole initialize event wrapper
├── EventMainServerCreate            # EasySwoole main server create event wrapper
└── function.php                     # Common function library (overridable)
```

---

## 🚦 Quick Start

### Controller Example

Build admin controllers quickly with Trait, built-in CRUD operations:

```php
<?php
namespace App\HttpController\Admin;

use BasicHub\EsCore\HttpController\Admin\AuthTrait;

class UserController
{
    use AuthTrait;
    
    // AuthTrait provides the following methods:
    // - index()       List query (pagination, search, sorting)
    // - export()      Export (full export, async export)
    // - add()         Add data
    // - edit()        Edit data
    // - delete()      Delete data
}
```

### Model Example

Build data models quickly with built-in common query methods and cache support:

```php
<?php
namespace App\Model;

use BasicHub\EsCore\Model\BaseModelTrait;
use EasySwoole\ORM\AbstractModel;

class UserModel extends AbstractModel
{
    use BaseModelTrait;
    
    protected $tableName = 'users';
    
    // BaseModelTrait provides:
    // - Soft delete support
    // - Auto timestamps
    // - Model caching
    // - Batch operations
    // - Relational queries, etc.
}
```

### JWT Authentication Example

```php
<?php
use BasicHub\EsCore\Common\Classes\Jwt;

// Generate Token
$jwt = new Jwt($secretKey);
$token = $jwt->encode([
    'user_id' => 1001,
    'username' => 'john',
    'exp' => time() + 7200
]);

// Verify Token
$payload = $jwt->decode($token);
if ($payload) {
    echo "User ID: " . $payload['user_id'];
}
```

---

## 🔧 Common Commands

### Process Management

```bash
# View process information
php easyswoole process show -d

# Kill specific process
php easyswoole process kill --pid=PID

# Kill specific process group
php easyswoole process kill --group=GROUP_NAME

# Kill all processes
php easyswoole process killAll

# Force kill process (with -f parameter)
php easyswoole process kill --pid=PID -f
```

### Task Management

```bash
# View Task process status
php easyswoole task status
```

---

## ❓ FAQ

<details>
<summary><b>Why isn't function.php in this project's composer.json?</b></summary>

`function.php` should be defined by the business project's `composer.json` to control the loading order. It must be loaded after the project's custom functions, otherwise pre-defined functions cannot be overridden. If placed in this project's `composer.json`, it will be loaded first, losing flexibility.

</details>

<details>
<summary><b>Why use Traits instead of inheritance?</b></summary>

Traits and inheritance each have their advantages and disadvantages. The main purpose of choosing Traits is to **not break EasySwoole's recommended inheritance relationships**, maintaining framework flexibility and extensibility.

</details>

<details>
<summary><b>What should I be aware of when using Traits?</b></summary>

1. **Properties cannot be overridden** - Defined `setTraitProtected` method to modify Trait properties
2. **Methods cannot be overloaded** - Need to adjust code encapsulation in time to avoid maintenance difficulties from wholesale copying
3. **Method name prefix mechanism** - Controller public methods have fixed prefixes by default, implemented through `BaseControllerTrait.php`'s `actionNotFound` method for flexible invocation

</details>

<details>
<summary><b>How to install CloudLib dependencies?</b></summary>

Starting from the latest version, optional dependencies are now installed on-demand in business projects, no longer uniformly included, significantly reducing vendor size.

Please copy the `composer require` command from the respective class declarations to install the required dependencies:
- For Tencent Cloud: Install Tencent Cloud SDK
- For Alibaba Cloud: Install Alibaba Cloud SDK
- Install on-demand to avoid redundancy

</details>

---

## 🆕 Version Updates

### Major Changes

#### 🔐 Security Enhancements
- **Independent Extension Parameter Storage** - No longer directly modifies EasySwoole's Request object, stored in controller properties to avoid signature verification issues
- **Independent RSA Decryption** - Forces critical data to be RSA-decrypted to prevent malicious attacks

#### 🏗️ Architecture Refactoring
- ✅ Rewrote request tracing system with sub-trace and async task tracing support
- ✅ Rewrote JWT Token related functions and classes
- ✅ Rewrote coroutine context manager for enhanced performance
- ✅ Rewrote Openssl encryption/decryption supporting multiple encryption methods (extensible)
- ✅ Rewrote GeoIP parsing supporting multiple channels (extensible)
- ✅ Refactored custom consumer processes supporting multi-task per process

#### ⚡ Performance Optimization
- Removed all `eval` dynamic parsing for improved security and performance
- Optimized Redis cluster key distribution rules and data sharding
- Added client temporary credential direct upload to object storage, supporting ultra-large file uploads (theoretically unlimited)

#### 🔌 Extension Support
- Model-related adjustments for better ORM best practices
- CloudLib added multi-cloud vendor support (Tencent Cloud, Alibaba Cloud)
- Notify component completely rewritten supporting multi-platform, multi-task

#### 📦 Dependency Optimization
- **On-demand Optional Dependencies** - CloudLib related Composer dependencies are now installed on-demand by business projects
- Significantly reduced vendor size, faster and lighter CI/CD

### TODO List

- [x] Create Crontab scheduled tasks and Consumer consumption tasks
- [x] Rewrote ShardTable class as TablePart class
- [x] WebSocket related classes (events, parsing, Caller, connection management)
- [x] Crontab supports database, file, http and other acquisition methods
- [x] es-orm-cache component encapsulation
- [x] Rewrote verify_token to object-oriented style
- [x] CloudLib dependencies installed on-demand by business layer projects

---

## 📚 Related Documentation

- [Trait Conflict Resolution](https://www.php.net/manual/en/language.oop5.traits.php)
- [XlsWriter Documentation](https://xlswriter-docs.viest.me/en)

---

## 🤝 Contributing

We welcome all forms of contributions, whether new features, bug fixes, or documentation improvements.

### Contribution Guidelines

1. Fork this repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Contribution Leaderboard

<a href="https://openomy.com/basic-hub/es-core" target="_blank" style="display: block; width: 100%;margin-bottom: 1rem;" align="center">
  <img src="https://openomy.com/svg?repo=basic-hub/es-core&chart=bubble&latestMonth=12" target="_blank" alt="Contribution Leaderboard" style="display: block; width: 100%;" />
</a>

### All Contributors

<a href="https://github.com/basic-hub/es-core/graphs/contributors">
  <img alt="Contributors" src="https://contrib.rocks/image?repo=basic-hub/es-core" />
</a>

---

## ⭐ Star History

[![Star History Chart](https://api.star-history.com/svg?repos=basic-hub/es-core&type=Date)](https://star-history.com/#basic-hub/es-core&Date)

---

## 💖 Acknowledgments

Thanks to [JetBrains](https://www.jetbrains.com/) for providing free IDE licenses for open source projects

[<img src="https://resources.jetbrains.com/storage/products/company/brand/logos/jetbrains.png" alt="JetBrains Logo" height="100" />](https://www.jetbrains.com/)

---

## 📄 License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## 📞 Contact Us

- **Issues**: [GitHub Issues](https://github.com/basic-hub/es-core/issues)
- **Discussions**: [GitHub Discussions](https://github.com/basic-hub/es-core/discussions)

---

<div align="center">

**If this project helps you, please give us a ⭐ Star!**

Made with ❤️ by Basic Hub Team

</div>
