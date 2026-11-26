# 通知推送系统使用文档

## 目录结构

```
Notify/
├── NotifyFactory.php          # 工厂类，定义平台和类型常量
├── Message/                   # 各平台消息类
│   ├── Feishu/               # 飞书消息
│   ├── Dingtalk/             # 钉钉消息
│   ├── WeCom/                # 企业微信消息
│   └── Wechat/               # 微信消息
├── App/                       # 自建应用推送
│   ├── Feishu.php
│   ├── Dingtalk.php
│   ├── WeCom.php
│   └── Wechat.php
├── Webhook/                   # Webhook 推送
│   ├── Feishu.php
│   ├── Dingtalk.php
│   └── WeCom.php
└── Template/                  # 模板消息推送
    └── Wechat.php
```

## 配置说明

### 扁平化配置结构

在 `env.php` 中配置 `ES_NOTIFY`:

```php
'ES_NOTIFY' => [
    // 启用的实例列表（driver 配置项）
    'driver' => ['feishu_webhook', 'dingtalk_app', 'wechat_tpl_default'],
    
    // 各实例配置（扁平化，所有配置在同一层级）
    'feishu_webhook' => [
        'type' => \BasicHub\EsCore\Common\CloudLib\Notify\NotifyFactory::TYPE_WEBHOOK,
        'platform' => \BasicHub\EsCore\Common\CloudLib\Notify\NotifyFactory::PLATFORM_FEISHU,
        'secret' => 'your_secret',
        'webhook' => 'https://open.feishu.cn/open-apis/bot/v2/hook/xxx',
    ],
    
    'feishu_app' => [
        'type' => \BasicHub\EsCore\Common\CloudLib\Notify\NotifyFactory::TYPE_APP,
        'platform' => \BasicHub\EsCore\Common\CloudLib\Notify\NotifyFactory::PLATFORM_FEISHU,
        'app_id' => 'your_app_id',
        'app_secret' => 'your_app_secret',
        'receive_id_type' => 'chat_id', // chat_id|open_id|user_id
        'receive_id' => 'oc_xxx',
    ],
    
    'dingtalk_app' => [
        'type' => \BasicHub\EsCore\Common\CloudLib\Notify\NotifyFactory::TYPE_APP,
        'platform' => \BasicHub\EsCore\Common\CloudLib\Notify\NotifyFactory::PLATFORM_DINGTALK,
        'app_key' => 'your_app_key',
        'app_secret' => 'your_app_secret',
        'agent_id' => 'your_agent_id',
        'userid_list' => 'user1,user2',
    ],
    
    'wechat_tpl_default' => [
        'type' => \BasicHub\EsCore\Common\CloudLib\Notify\NotifyFactory::TYPE_TEMPLATE,
        'platform' => \BasicHub\EsCore\Common\CloudLib\Notify\NotifyFactory::PLATFORM_WECHAT,
        'appid' => 'your_appid',
        'secret' => 'your_secret',
        'template_id' => 'template_id',
        'openid' => 'user_openid',
    ],
],
```

### 配置要点

1. **实例名称自定义**：如 `feishu_webhook`、`feishu_app`、`wechat_tpl_default` 等（随便叫啥，类型和平台在下方配置即可）
2. **必填字段**：`type`（推送类型）和 `platform`（平台）
3. **driver 配置**：数组形式，存储要启用的实例名列表
4. **同平台多实例**：可以配置多个实例（如 `feishu_webhook` 和 `feishu_app`），调用时会自动发送到所有实例

## 使用方法

### 1. notify_message() - 发送单个消息到指定实例

**函数签名：**
```php
function notify_message(MessageInterface $message, string $instance, $async = true)
```

**使用场景：**
- 明确知道要发送到哪个实例
- 只需要发送单个平台的通知
- 需要精确控制发送目标

**示例：**
```php
use BasicHub\EsCore\Common\CloudLib\Notify\Message\Feishu\CardError;

// 发送飞书错误卡片到指定实例
$message = new CardError([
    'servername' => '生产服务器',
    'project' => 'API 服务',
    'datetime' => date('Y-m-d H:i:s'),
    'trigger' => 'HTTP 请求',
    'filename' => 'Controller.php 第 100 行',
    'content' => '数据库连接超时',
    'isAtAll' => true,
]);

notify_message($message, 'feishu_webhook');
```

**注意事项：**
- ⚠️ **`$instance` 参数必传**，因为不同平台的 Message 类不通用
- 实例名必须在 `ES_NOTIFY.driver` 中配置且启用
- 传递错误的实例名会记录错误日志并返回 false

---

### 2. notify_message_list() - 批量发送多平台消息

**函数签名：**
```php
function notify_message_list(array $messages, $instance = null, $async = true)
```

**使用场景：**
- 需要同时发送多个平台的通知
- 业务层不关心具体配置了哪些实例
- 让框架自动匹配平台并发送

**示例：**
```php
use BasicHub\EsCore\Common\CloudLib\Notify\NotifyFactory;
use BasicHub\EsCore\Common\CloudLib\Notify\Message\Feishu\CardError;
use BasicHub\EsCore\Common\CloudLib\Notify\Message\Dingtalk\Markdown;
use BasicHub\EsCore\Common\CloudLib\Notify\Message\WeCom\Markdown as WeComMarkdown;
use BasicHub\EsCore\Common\CloudLib\Notify\Message\Wechat\Notice;

// 准备各平台的消息
$messages = [
    // 飞书卡片
    NotifyFactory::PLATFORM_FEISHU => new CardError([
        'servername' => '生产服务器',
        'project' => 'API 服务',
        'datetime' => date('Y-m-d H:i:s'),
        'trigger' => 'HTTP 请求',
        'filename' => 'Controller.php 第 100 行',
        'content' => '数据库连接超时',
        'isAtAll' => true,
    ]),
    
    // 钉钉 Markdown
    NotifyFactory::PLATFORM_DINGTALK => new Markdown([
        'title' => '系统错误',
        'text' => "### 系统错误\n- 服务器：生产服务器\n- 项目：API 服务\n- 详情：数据库连接超时",
        'isAtAll' => true,
    ]),
    
    // 企业微信 Markdown
    NotifyFactory::PLATFORM_WECOM => new WeComMarkdown([
        'content' => "### 系统错误\n- 服务器：生产服务器\n- 项目：API 服务\n- 详情：数据库连接超时",
        'isAtAll' => true,
    ]),
    
    // 微信模板消息
    NotifyFactory::PLATFORM_WECHAT => new Notice([
        'content' => '系统错误: 数据库连接超时，文件 Controller.php 第 100 行',
    ]),
];

// 自动发送到所有配置的平台实例
notify_message_list($messages);
```

**工作原理：**
1. 读取 `ES_NOTIFY.driver` 获取所有启用的实例
2. 按 `platform` 字段将实例分组
3. 遍历传入的 `$messages` 数组
4. 匹配平台，将消息发送到该平台的**所有实例**
5. 返回发送结果数组（key 为实例名，value 为发送结果）

**注意事项：**
- 如果某个平台未配置或未启用，会自动跳过该平台
- 如果一个平台配置了多个实例（如 `feishu_webhook` 和 `feishu_app`），会发送到所有实例
- `$messages` 的 key 必须使用 `NotifyFactory::PLATFORM_*` 常量

---

### 3. notify_table() - 发送表格通知

**函数签名：**
```php
function notify_table($title, $headers, $data, $instance = null, $options = [], $async = true)
```

**使用场景：**
- 需要发送表格数据
- 自动适配各平台的表格格式
- 简化表格通知的发送流程

**示例：**
```php
// 表头（飞书风格，支持 display_name）
$headers = [
    ['display_name' => '用户ID', 'key' => 'user_id'],
    ['display_name' => '用户名', 'key' => 'username'],
    ['display_name' => '状态', 'key' => 'status'],
];

// 表格数据
$data = [
    ['user_id' => 1001, 'username' => '张三', 'status' => '正常'],
    ['user_id' => 1002, 'username' => '李四', 'status' => '异常'],
];

// 发送表格通知
notify_table('用户状态报告', $headers, $data);
```

**工作原理：**
1. 自动为所有平台创建对应的 Table Message 对象
2. 自动转换 headers 格式（飞书 → 钉钉/企业微信）
3. 调用 `notify_message_list()` 批量发送

**注意事项：**
- headers 使用飞书风格（支持 `display_name` 和 `key`）
- 钉钉和企业微信会自动提取 `display_name` 作为表头
- 微信模板消息不支持表格，该平台会被跳过

---

### 4. notify_instance() - 获取通知实例

**函数签名：**
```php
function notify_instance($instance = null, $type = null)
```

**使用场景：**
- 需要直接操作通知实例对象
- 需要调用实例的特定方法
- 进行批量实例管理

**示例：**
```php
// 获取所有启用的实例
$instances = notify_instance();
// 返回: ['feishu_webhook' => FeishuObject, 'dingtalk_app' => DingtalkObject, ...]

// 获取指定实例
$instance = notify_instance('feishu_webhook');
// 返回: ['feishu_webhook' => FeishuObject]

// 获取指定类型的所有实例
$webhooks = notify_instance(null, NotifyFactory::TYPE_WEBHOOK);
// 返回所有 Webhook 类型的实例
```

## 平台与类型常量

### 平台常量（NotifyFactory::PLATFORM_*）

```php
use BasicHub\EsCore\Common\CloudLib\Notify\NotifyFactory;

NotifyFactory::PLATFORM_FEISHU    // 飞书
NotifyFactory::PLATFORM_DINGTALK  // 钉钉
NotifyFactory::PLATFORM_WECOM     // 企业微信
NotifyFactory::PLATFORM_WECHAT    // 微信
```

### 类型常量（NotifyFactory::TYPE_*）

```php
NotifyFactory::TYPE_APP       // 自建应用
NotifyFactory::TYPE_WEBHOOK   // Webhook
NotifyFactory::TYPE_TEMPLATE  // 模板消息
```

## 消息类说明

### 飞书（Feishu）消息类

- `Feishu\CardError` - 错误卡片（支持时区）
- `Feishu\CardErrorTimeZone` - 带时区显示的错误卡片
- `Feishu\Table` - 表格消息
- 其他自定义卡片...

### 钉钉（Dingtalk）消息类

- `Dingtalk\Markdown` - Markdown 消息
- `Dingtalk\Table` - 表格消息
- `Dingtalk\Text` - 文本消息
- 其他类型...

### 企业微信（WeCom）消息类

- `WeCom\Markdown` - Markdown 消息
- `WeCom\Table` - 表格消息
- `WeCom\Text` - 文本消息
- 其他类型...

### 微信（Wechat）消息类

- `Wechat\Notice` - 模板消息通知
- 其他模板类型...

## 最佳实践

### 1. 使用常量而非字符串

❌ **不推荐：**
```php
$messages = [
    'Feishu' => new CardError([...]),
];
```

✅ **推荐：**
```php
use BasicHub\EsCore\Common\CloudLib\Notify\NotifyFactory;

$messages = [
    NotifyFactory::PLATFORM_FEISHU => new CardError([...]),
];
```

### 2. 单平台通知使用 notify_message()

❌ **不推荐：**
```php
notify_message_list([
    NotifyFactory::PLATFORM_FEISHU => $message,
]);
```

✅ **推荐：**
```php
notify_message($message, 'feishu_webhook');
```

### 3. 多平台通知使用 notify_message_list()

❌ **不推荐：**
```php
notify_message($feishuMessage, 'feishu_webhook');
notify_message($dingtalkMessage, 'dingtalk_app');
notify_message($wecomMessage, 'wecom_webhook');
```

✅ **推荐：**
```php
notify_message_list([
    NotifyFactory::PLATFORM_FEISHU => $feishuMessage,
    NotifyFactory::PLATFORM_DINGTALK => $dingtalkMessage,
    NotifyFactory::PLATFORM_WECOM => $wecomMessage,
]);
```

### 4. 异步发送避免阻塞

```php
// 异步发送（默认，推荐）
notify_message($message, 'feishu_webhook', true);

// 同步发送（需要立即获取结果时使用）
$result = notify_message($message, 'feishu_webhook', false);
```

### 5. 错误处理

```php
// 同步模式可以获取发送结果
$result = notify_message($message, 'feishu_webhook', false);
if ($result === false) {
    // 发送失败，查看日志
    trace('通知发送失败', 'error');
}

// 批量发送结果
$results = notify_message_list($messages, null, false);
foreach ($results as $instance => $result) {
    if ($result === false) {
        trace("实例 {$instance} 发送失败", 'error');
    }
}
```

## 注意事项

### 重要提醒

1. **`notify_message()` 的 `$instance` 参数必传**
   - 不同平台的 Message 类不通用
   - 传递错误的实例名会导致发送失败

2. **配置实例必须在 driver 中启用**
   ```php
   'driver' => ['feishu_webhook', 'dingtalk_app'], // 必须配置
   ```

3. **同平台多实例会全部发送**
   - 如果配置了 `feishu_webhook` 和 `feishu_app`
   - 调用 `notify_message_list()` 时，飞书消息会发送到两个实例

4. **Message 类必须匹配平台**
   - 飞书实例只能发送飞书 Message
   - 钉钉实例只能发送钉钉 Message
   - 以此类推...

5. **协程环境自动处理**
   - 在协程环境中使用 `go()` 创建协程
   - 非协程环境直接执行
   - 无需手动判断环境

### 常见问题

**Q: 为什么 notify_message() 必须传递 $instance？**  
A: 因为不同平台的 Message 类不通用（如 `Feishu\CardError` 只能发飞书），无法通过 Message 对象自动推断目标实例。

**Q: 如何发送到多个实例？**  
A: 使用 `notify_message_list()`，它会自动将消息发送到匹配平台的所有实例。

**Q: 配置了实例但没有收到通知？**  
A: 检查以下几点：
   1. 实例名是否在 `ES_NOTIFY.driver` 中配置
   2. 实例配置是否正确（webhook、app_id 等）
   3. 查看错误日志 `trace()` 输出

**Q: 如何测试通知发送？**  
A: 使用同步模式并检查返回值：
```php
$result = notify_message($message, 'feishu_webhook', false);
var_dump($result);
```

## 迁移指南

### 从旧版配置迁移

**旧版配置（层级结构）：**
```php
'ES_NOTIFY' => [
    'driver' => ['Feishu', 'Dingtalk'],
    'Feishu' => [
        'Webhook' => [...],
        'App' => [...],
    ],
]
```

**新版配置（扁平结构）：**
```php
'ES_NOTIFY' => [
    'driver' => ['feishu_webhook', 'feishu_app'],
    'feishu_webhook' => [
        'type' => NotifyFactory::TYPE_WEBHOOK,
        'platform' => NotifyFactory::PLATFORM_FEISHU,
        ...
    ],
    'feishu_app' => [
        'type' => NotifyFactory::TYPE_APP,
        'platform' => NotifyFactory::PLATFORM_FEISHU,
        ...
    ],
]
```

### 代码迁移

**旧版调用方式已废弃，请使用新版：**

```php
// ❌ 旧版（已废弃）
notify_xx('Feishu', 'Webhook', $data);

// ✅ 新版
notify_message($message, 'feishu_webhook');
```

## 技术支持

如有问题，请查看：
1. 配置文件 `env.php` 中的 `ES_NOTIFY` 配置
2. 错误日志中的 `trace()` 输出
3. 本 README 文档的常见问题部分

---

**更新日期：** 2026-11-26  
**版本：** 2.0（扁平化配置版本）
