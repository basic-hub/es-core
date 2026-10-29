# NAS 文件管理

统一的 NAS 文件操作封装，屏蔽各厂商 API 差异。

## 与对象存储（Storage）的区别

| | 对象存储 | NAS |
|---|---|---|
| 路径模型 | 扁平 key | 真实目录树，有 mkdir / listDir 语义 |
| 鉴权 | AK/SK 无状态签名 | 登录拿 session，后续请求带 session |
| 能力一致性 | 各家基本一致 | 差异很大，需按 `can()` 降级 |

## 目录结构

| 文件 | 说明 |
|---|---|
| `NasInterface.php` | 统一接口 |
| `Base.php` | 驱动基类，提供 HTTP 请求、路径处理、能力声明等公共实现 |
| `Synology.php` | 群晖 DSM（File Station API）驱动 |
| `QfinderPro.php` | 威联通 QTS（File Station API）驱动 |

## 快速开始

推荐用助手函数 `nas()`，它会按 `NAS` 配置项里的 `driver` 自动选择驱动：

```php
$nas = nas();

// 也可以临时覆盖配置
$nas = nas(['host' => 'https://192.168.1.200:5001']);
```

直接实例化同样可以：

```php
use BasicHub\EsCore\Common\CloudLib\Nas\Synology;

$nas = new Synology([
    'host'     => 'https://192.168.1.200:5001',
    'username' => 'admin',
    'password' => 'password123',
]);
```

## 配置项

公共配置（`Base` 定义）：

| 键 | 默认值 | 说明 |
|---|---|---|
| `host` | 无，必填 | NAS 地址，**必须带协议前缀和端口**，如 `https://192.168.1.200:5001` |
| `username` | 无 | 登录账号 |
| `password` | 无 | 登录密码 |
| `otpCode` | `''` | 两步验证动态码，未开启 2FA 时留空 |
| `sslVerify` | `false` | 是否校验 SSL 证书。NAS 多为内网自签证书，默认关闭 |
| `connectTimeout` | `10.0` | 连接超时（秒） |
| `readTimeout` | `300.0` | 读写超时（秒），传大文件需调大 |
| `taskInterval` | `0.5` | 异步任务轮询间隔（秒） |
| `taskMaxTimes` | `600` | 异步任务最大轮询次数，超出视为超时 |
| `maxReadSize` | `10485760` | `read()` 默认允许的最大字节数（10MB） |

群晖专有：

| 键 | 默认值 | 说明 |
|---|---|---|
| `session` | `FileStation` | 会话名，决定会话凭证的作用范围 |

威联通专有：

| 键 | 默认值 | 说明 |
|---|---|---|
| `forceDelete` | `false` | `true` 直接删除，`false` 移入回收站 |
| `duplicate` | `overwrite` | 同名冲突策略：`skip` / `overwrite` / `rename` |

`host` 不带协议前缀会在实例化时直接抛异常，这是最常见的配置错误。

## 能力矩阵

各厂商 API 能力差异大，调用前先用 `can()` 判断，不支持的方法会抛「不支持」异常。

| 能力 | 群晖 Synology | 威联通 QfinderPro |
|---|---|---|
| `listDir` 列目录 | 支持 | 支持 |
| `exists` 判断存在 | 支持 | 支持（无 stat 接口，靠列父目录匹配名称） |
| `mkdir` 建目录 | 支持 | 支持 |
| `upload` 上传 | 支持 | 支持 |
| `download` 下载 | 支持 | 支持 |
| `delete` 删除 | 支持（异步任务） | 支持 |
| `copy` 复制 | 支持（异步任务） | 支持 |
| `move` 移动 | 支持（异步任务） | 支持 |
| `rename` 重命名 | 支持 | 支持 |
| `read` 读取内容 | 支持 | 未实现 |
| `thumbnail` 缩略图 | 支持 | 未实现 |
| `previewUrl` 预览地址 | 支持 | 未实现 |

```php
if ($nas->can('thumbnail')) {
    $jpg = $nas->thumbnail('/home/photo/a.jpg');
}
```

威联通的 `read` / `thumbnail` / `previewUrl` 没有实现 —— QNAP 有类似接口，但未经真机验证，不臆造参数。

## API 说明与示例

所有方法失败时抛 `\Exception`，不返回 false，调用方用 try/catch 兜住即可。路径一律用 NAS 绝对路径，内部会统一规范化（反斜线转正斜线、去重复斜线、去末尾斜线）。

### 会话

```php
// 登录。重复调用会复用已有会话，传 true 强制重新登录
$sid = $nas->login();
$sid = $nas->login(true);

// 注销
$nas->logout();
```

其余方法都会在需要时自动登录，一般不用手动调 `login()`。

### listDir 列目录

```php
$result = $nas->listDir('/home/doc', [
    'limit'          => 100,    // 0 表示全部
    'offset'         => 0,
    'sort_by'        => 'name', // name|size|mtime
    'sort_direction' => 'asc',
]);

// 返回结构
// [
//     'total' => 42,
//     'list'  => [
//         [
//             'name'  => 'a.jpg',
//             'path'  => '/home/doc/a.jpg',
//             'isDir' => false,
//             'size'  => 10240,
//             'mtime' => 1730000000,
//             'raw'   => [...],   // 厂商原始数据
//         ],
//     ],
// ]

foreach ($result['list'] as $item) {
    echo $item['isDir'] ? "[目录] " : "[文件] ";
    echo $item['name'], PHP_EOL;
}
```

群晖传 `/` 时会自动改用 `list_share` 列出共享文件夹 —— DSM 的根目录就是共享文件夹列表，和子目录用的不是同一个接口。

### exists 判断存在

```php
if (!$nas->exists('/home/doc/a.jpg')) {
    throw new \Exception('文件不存在');
}
```

### mkdir 建目录

注意参数是「父目录 + 新目录名」两段，不是完整路径：

```php
$nas->mkdir('/home', 'doc');                            // 得到 /home/doc
$nas->mkdir('/home/a/b', 'c', ['force_parent' => true]); // 父目录不存在时一并创建
```

### upload 上传

```php
// 最简：上传到目标目录，文件名取本地 basename
$nas->upload('/tmp/a.apk', '/home/apk');

$nas->upload('/tmp/a.apk', '/home/apk', [
    'filename'       => 'release.apk', // 自定义存到 NAS 的文件名
    'overwrite'      => true,          // 默认 true，false 为跳过已存在文件
    'create_parents' => true,          // 目标目录不存在时一并创建
    'mtime'          => 1730000000000, // 毫秒级时间戳，不传则由 NAS 决定
]);
```

大文件是流式上传的，内存占用固定在 1MB 左右，不随文件大小增长。实现上绕开了 HttpClient，直接用 `\Swoole\Coroutine\Client` 手拼 multipart 请求、按 1MB 分块写 socket —— 用 `file_get_contents` 拼字符串会导致内存占用约为文件大小的两倍，几百 MB 的文件就能把进程打爆。

传大文件记得把 `readTimeout` 调大。

### download 下载

```php
$nas->download('/home/apk/a.apk', '/tmp/a.apk');

// mode=download 触发附件下载语义，mode=open 为内联出流
$nas->download('/home/apk/a.apk', '/tmp/a.apk', ['mode' => 'download']);
```

下载是边收边写盘的真流式，大文件没有内存压力。本地目录不存在会自动创建。

### read 读取文件内容

```php
$text = $nas->read('/home/conf/app.json');
$data = json_decode($text, true);

// 放宽到 50MB
$text = $nas->read('/home/log/big.log', ['maxSize' => 52428800]);
```

内容会完整进内存，所以**读取前会先用 getinfo 查一次文件大小**，超过上限直接抛异常并提示改用 `download()`。必须事前检查 —— 等读完再判断，内存已经占掉了。默认上限 10MB，可以用 `maxSize` 选项或 `maxReadSize` 配置调整。

目标是目录时抛异常。

### previewUrl 预览地址

返回一个完整 HTTP 地址，直接塞进 `img` / `video` 标签即可，由 NAS 向浏览器出流，不经过 PHP 转发：

```php
$url = $nas->previewUrl('/home/photo/a.jpg');
echo '<img src="' . htmlspecialchars($url) . '">';

$url = $nas->previewUrl('/home/video/a.mp4');
echo '<video src="' . htmlspecialchars($url) . '" controls></video>';

// 强制触发浏览器下载而非内联预览
$url = $nas->previewUrl('/home/apk/a.apk', ['mode' => 'download']);
```

视频拖动进度依赖 NAS 对 Range 请求的支持，群晖原生支持。

**安全提醒**：地址里含会话凭证（群晖是 `_sid`），等同于当前账号的文件访问权限。下发给浏览器前最好自己包一层短时效的代理地址，不要长期缓存，也不要给不可信的客户端。

### thumbnail 缩略图

返回缩略图二进制内容，适合服务端缓存后再下发：

```php
$jpg = $nas->thumbnail('/home/photo/a.jpg', [
    'size'   => 'medium', // small|medium|large|original，默认 medium
    'rotate' => 0,        // 旋转档位 0-4
]);

file_put_contents('/tmp/thumb.jpg', $jpg);
```

依赖 NAS 自身的索引服务。视频被索引后也会有封面图。未被索引或格式不支持时抛异常。`size` 传了白名单外的值会直接快速失败，不发请求。

图片视频预览有两条路，按场景选：`previewUrl()` 不占 PHP 进程，适合直接给前端；`thumbnail()` 拿到二进制，适合自己做缓存层。

### delete 删除

```php
// 删单个
$nas->delete('/home/doc/a.jpg');

// 批量删（两家驱动都支持传数组）
$nas->delete(['/home/doc/a.jpg', '/home/doc/b.jpg']);

// 群晖：recursive 默认 true，删非空目录需要它
$nas->delete('/home/doc', ['recursive' => true]);

// 威联通：force=true 直接删，false 移入回收站
$nas->delete('/home/doc/a.jpg', ['force' => true]);
```

群晖的删除是异步任务，内部已经轮询到任务完成才返回，调用方拿到 `true` 时删除已经真正结束。轮询受 `taskInterval` 和 `taskMaxTimes` 控制，默认最长等 300 秒。

威联通的批量操作要求所有路径在同一目录下，驱动内部会按目录分组后分批提交。

### copy / move 复制移动

目标参数是**目录**，不是完整的目标路径：

```php
$nas->copy('/home/doc/a.jpg', '/home/backup');
$nas->move('/home/doc/a.jpg', '/home/archive');

// 批量
$nas->move(['/home/doc/a.jpg', '/home/doc/b.jpg'], '/home/archive');

// 同名冲突时覆盖
$nas->copy('/home/doc/a.jpg', '/home/backup', ['overwrite' => true]);
```

群晖不传 `overwrite` 时遇同名文件会直接报错（1003），所以驱动默认显式传 `false` 走跳过。同样是异步任务，内部已轮询至完成。

### rename 重命名

只改名，不跨目录。跨目录请用 `move()`：

```php
$nas->rename('/home/doc/a.jpg', 'b.jpg');
```

## 完整示例

```php
<?php

use BasicHub\EsCore\Common\CloudLib\Nas\Synology;

// 必须在协程环境中运行
\Swoole\Coroutine\run(function () {

    $nas = new Synology([
        'host'        => 'https://192.168.1.200:5001',
        'username'    => 'admin',
        'password'    => 'password123',
        'readTimeout' => 600.0,   // 要传大文件，调大读写超时
    ]);

    try {
        // 1. 建目录
        if (!$nas->exists('/home/demo')) {
            $nas->mkdir('/home', 'demo');
        }

        // 2. 上传
        $nas->upload('/tmp/photo.jpg', '/home/demo', ['overwrite' => true]);

        // 3. 列目录
        $result = $nas->listDir('/home/demo', ['limit' => 50]);
        echo "共 {$result['total']} 项", PHP_EOL;
        foreach ($result['list'] as $item) {
            printf("%s %s (%d 字节)%s", $item['isDir'] ? '[D]' : '[F]', $item['name'], $item['size'], PHP_EOL);
        }

        // 4. 下载
        $nas->download('/home/demo/photo.jpg', '/tmp/download.jpg');

        // 5. 读取文本内容（小文件）
        if ($nas->exists('/home/demo/config.json')) {
            $conf = json_decode($nas->read('/home/demo/config.json'), true);
        }

        // 6. 预览地址，交给前端直连
        $imgUrl   = $nas->previewUrl('/home/demo/photo.jpg');
        $videoUrl = $nas->previewUrl('/home/demo/movie.mp4');

        // 7. 缩略图，落地本地缓存
        if ($nas->can('thumbnail')) {
            $thumb = $nas->thumbnail('/home/demo/photo.jpg', ['size' => 'medium']);
            file_put_contents('/tmp/thumb.jpg', $thumb);
        }

        // 8. 复制、改名、移动
        $nas->copy('/home/demo/photo.jpg', '/home/demo/backup');
        $nas->rename('/home/demo/photo.jpg', 'photo_old.jpg');
        $nas->move('/home/demo/photo_old.jpg', '/home/archive');

        // 9. 删除
        $nas->delete('/home/demo/backup/photo.jpg');
        $nas->delete(['/home/demo/a.txt', '/home/demo/b.txt']);

        // 10. 注销
        $nas->logout();

    } catch (\Throwable $e) {
        echo 'NAS 操作失败: ', $e->getMessage(), PHP_EOL;
    }
});
```

控制器里用助手函数更简洁：

```php
public function preview()
{
    $path = $this->request()->getRequestParam('path');

    try {
        $nas = nas();
        if (!$nas->exists($path)) {
            return $this->writeJson(404, [], '文件不存在');
        }
        return $this->writeJson(200, ['url' => $nas->previewUrl($path)]);
    } catch (\Throwable $e) {
        return $this->writeJson(500, [], $e->getMessage());
    }
}
```

## 扩展新驱动

继承 `Base`，实现 `NasInterface`。`Base` 已经提供的公共能力：

| 方法 | 说明 |
|---|---|
| `request()` / `requestJson()` | 普通 HTTP 请求 |
| `requestContent()` | 取回二进制响应体，同时带出响应头 |
| `requestDownload()` | 流式下载至本地文件 |
| `requestUpload()` | 流式上传，内存占用固定 |
| `detectJsonError()` | 判断响应体是否为 JSON 错误信封 |
| `normalizePath()` / `splitPath()` | 路径规范化与拆分 |
| `buildUrl()` / `buildQuery()` | URL 拼接，数组值展开为重复同名参数 |
| `formatEntry()` | 统一目录项结构 |
| `sleep()` | 兼容协程与非协程环境 |
| `unsupported()` | 未实现能力时统一抛异常 |

`rename` / `read` / `thumbnail` / `previewUrl` 在 `Base` 里有默认的「抛不支持」实现，新驱动不实现也能跑。实现了就在 `$abilities` 里把对应开关置为 `true`：

```php
class MyNas extends Base
{
    protected $abilities = [
        'listDir'    => true,
        'exists'     => true,
        'mkdir'      => true,
        'upload'     => true,
        'download'   => true,
        'delete'     => true,
        'copy'       => true,
        'move'       => true,
        'rename'     => false,  // 该厂商没有重命名接口
        'read'       => true,
        'thumbnail'  => false,
        'previewUrl' => true,
    ];
}
```

## 实现笔记

几个踩过的坑，记下来避免重犯。

**群晖的 entry.cgi 只从 URL 查询串解析路由参数。** `api` / `version` / `method` 这三个必须放 URL，放进 multipart body 会被判为参数无效（错误码 101）。官方文档的示例在这点上有误导性。

**Swoole 的 `addData($data, $name)` 语义是「从内存添加一个上传文件」**，生成的段带 `filename` 属性。用它提交普通表单字段会让 DSM 把所有字段都当成上传文件，于是拿不到 `path`，服务端持续等数据直到超时（错误码 1801，DSM 默认等 3600 秒）。所以上传是手拼 multipart body 的。

**EasySwoole 的 `HttpClient::setHeader()` 默认 `$strtolower = true`，会把 header 的值一起转小写**，这会破坏 multipart 的 boundary 大小写 —— body 里的分隔线保持原样，服务端就永远匹配不到结束边界，表现为上传卡死。规避方式是用 `setHeaders($headers, true, false)`。

**Content-Type 不足以判定错误。** 下载一个真实的 `.json` 文件时，响应的 content-type 同样是 `application/json`。所以 `detectJsonError()` 会进一步要求 JSON 结构长得像错误信封（带 `success=false` 或非成功的 `status`）才算失败，否则下载正常的 JSON 文件会直接报错。

**群晖的 API 版本不能硬编码。** DSM6 和 DSM7 的接口 path 与 version 差异很大，驱动通过 `SYNO.API.Info` 动态发现，取「文档已定义上限」与「设备 maxVersion」的较小值，再保证不低于 `minVersion`。高版本的参数语义未必向下兼容，所以不直接用 maxVersion。

**群晖参数里的逗号要转义**，因为逗号是多值分隔符。但两类值不能转义：一是 `passwd` / `password`，按文档要求原样传，否则含逗号的密码会登录失败；二是已经是 JSON 数组形式的值（如 `path`、`name`），其中的逗号是 JSON 语法的一部分。

## 待办

- 视频的服务端 Range 代理（分片转发）还没做。目前视频预览靠 `previewUrl()` 让浏览器直连 NAS，如果要完全隐藏 NAS 地址和凭证，需要额外实现一个按 Range 转发的方法。
- 威联通的 `read` / `thumbnail` / `previewUrl` 待真机验证后补齐。

