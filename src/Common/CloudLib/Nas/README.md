# NAS 文件管理

统一的 NAS 文件操作封装，屏蔽各厂商 API 差异。

## 与对象存储（Storage）的区别

|       | 对象存储        | NAS                        |
|-------|-------------|----------------------------|
| 路径模型  | 扁平 key      | 真实目录树，有 mkdir / listDir 语义 |
| 鉴权    | AK/SK 无状态签名 | 登录拿 session，后续请求带 session  |
| 能力一致性 | 各家基本一致      | 差异很大，需按 `can()` 降级         |
| 成本    | 按量、按时计费     | 固定资产，一次投入                  |

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
| `uploadTicket` 签发直传凭据 | 支持 | 未实现 |
| `download` 下载 | 支持 | 支持 |
| `delete` 删除 | 支持（异步任务） | 支持 |
| `copy` 复制 | 支持（异步任务） | 支持 |
| `move` 移动 | 支持（异步任务） | 支持 |
| `rename` 重命名 | 支持 | 支持 |
| `read` 读取内容 | 支持 | 未实现 |
| `thumbnail` 缩略图 | 支持 | 未实现 |
| `previewUrl` 预览地址 | 支持 | 未实现 |
| `thumbnailUrl` 缩略图地址 | 支持 | 未实现 |

```php
if ($nas->can('thumbnail')) {
    $jpg = $nas->thumbnail('/home/photo/a.jpg');
}
```

威联通的 `read` / `thumbnail` / `thumbnailUrl` / `previewUrl` 没有实现 —— QNAP 有类似接口，但未经真机验证，不臆造参数。

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

### uploadTicket 浏览器直传

`upload()` 走的是「客户端 → 业务服务器 → NAS」两段路。大文件下这条路的瓶颈在第一段：为了绕开单请求体积限制，前端通常得切片上传到服务器，服务器再把几百个切片合并成一个完整文件才能转发给 NAS。合并 10GB 文件意味着读 10GB + 写 10GB 的纯磁盘拷贝，耗时长且期间没有任何数据流向 NAS，这一步是大文件上传的主要失败点。

`uploadTicket()` 让前端跳过服务器直接传给 NAS，第一段路和合并一起消失：

```php
$ticket = $nas->uploadTicket('/home/apk', [
    'overwrite'      => true,
    'create_parents' => true,
]);

// [
//     'url'        => 'https://nas:5001/webapi/entry.cgi?api=...&_sid=...',
//     'method'     => 'POST',
//     'file_field' => 'file',
//     'fields'     => ['path' => '/home/apk', 'create_parents' => 'false', 'overwrite' => 'true'],
// ]
return $ticket;
```

前端拿到后按 `fields` 拼 FormData，**文件段必须放在最后**（群晖要求二进制段位于所有参数之后）。下面给了 axios 和原生 `XMLHttpRequest` 两个版本，任选其一。

FormData 塞进去的是 `File` 对象，不是文件内容 —— 浏览器发送时按块从磁盘读、边读边写 socket，内存占用恒定在几 MB，不随文件大小增长，几十 GB 也不会把标签页撑爆。

**axios 版：**

```js
import axios from 'axios'

async function uploadToNas(file, ticket, onProgress) {
  const form = new FormData()
  // 先放业务参数，顺序不能和文件段颠倒
  Object.keys(ticket.fields).forEach(function (key) {
    form.append(key, ticket.fields[key])
  })
  form.append(ticket.file_field, file, file.name)

  // 必须用裸 axios，不能用项目里配过拦截器的那个实例
  return axios.post(ticket.url, form, {
    timeout: 0,                  // 大文件别设超时，默认值会掐断上传
    withCredentials: false,      // 凭证走 URL 里的 _sid，不要带 Cookie
    transformRequest: [function (data) { return data }], // 阻止被序列化
    onUploadProgress: function (e) {
      if (e.total) {
        onProgress(Math.round((e.loaded / e.total) * 100))
      }
    },
  })
}
```

用 axios 要避开四个坑，前三个都会让请求发不出去或被 NAS 拒掉：

- **别用项目全局的 axios 实例。** 业务实例通常配了 `baseURL`（会把 NAS 的绝对地址拼坏）、`Authorization` 头（自定义头会触发 CORS 预检，而 DSM 不响应 `OPTIONS`，请求直接失败）、以及统一解包响应的拦截器（群晖返回的是 `{success, data}`，和业务约定的结构不一样，会被解析错）。直传请求必须用 `axios.create()` 开一个干净实例或直接用裸 `axios`。
- **别自己设 `Content-Type`。** 传了 FormData 就让浏览器自动生成，它会带上 multipart 必需的 `boundary`。手写成 `multipart/form-data` 会丢掉 boundary，NAS 解不出分段。
- **`timeout` 要显式设 0。** axios 默认不超时，但很多项目在全局配了 10 秒、30 秒，大文件必然被掐。
- **`transformRequest` 覆盖掉。** 全局配置里如果有序列化逻辑，会把 FormData 转成别的东西。

`onUploadProgress` 底层就是 `xhr.upload.onprogress`，axios 在浏览器里走的是 XHR 适配器，所以进度能力和原生一致，不用退回裸 XHR。

**原生 XMLHttpRequest 版：** 不想为直传额外引依赖、或者嫌躲全局配置麻烦的，用原生写法同样可以，而且天然不受项目里 axios 全局配置的影响：

```js
function uploadToNas(file, ticket, onProgress) {
  return new Promise(function (resolve, reject) {
    const form = new FormData()
    // 先放业务参数，顺序不能和文件段颠倒
    Object.keys(ticket.fields).forEach(function (key) {
      form.append(key, ticket.fields[key])
    })
    form.append(ticket.file_field, file, file.name)

    const xhr = new XMLHttpRequest()
    xhr.open(ticket.method, ticket.url)
    // 默认就是 0（不超时），显式写出来避免被误改
    xhr.timeout = 0
    xhr.withCredentials = false

    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable) {
        onProgress(Math.round((e.loaded / e.total) * 100))
      }
    }
    xhr.onload = function () {
      // 没配 CORS 头时这里拿不到 responseText，只能靠状态码粗判
      try {
        resolve(JSON.parse(xhr.responseText))
      } catch (err) {
        resolve(null)
      }
    }
    xhr.onerror = function () {
      // 响应被 CORS 拦掉也会走到这里，不代表文件没传上去
      reject(new Error('上传请求失败'))
    }
    xhr.send(form)
  })
}
```

两种写法的差别只在开发体验：axios 代码短、错误处理顺手，但要小心全局配置；原生 XHR 啰嗦一点，换来的是行为完全可控。能力上没有区别 —— 进度、不超时、流式发送三者都具备。

两个版本都不要调 `setRequestHeader('Content-Type', ...)`，让浏览器自己生成带 boundary 的那一行。

上线前要先处理三件事，否则直传跑不通：

**一、DSM 要放 CORS 头。** DSM 默认不返回 `Access-Control-Allow-Origin`。注意 `multipart/form-data` 在 CORS 安全名单内，不触发预检，所以请求会真发出去、NAS 也会真收下文件，只是响应被浏览器拦掉 —— 表现为文件传成功了但前端报错，等于盲传。配置路径：DSM 控制台 > 登录门户 > 高级 > 反向代理，给 NAS 的访问入口加「自定义标题」：

| 标题名 | 值 |
|---|---|
| `Access-Control-Allow-Origin` | 你的前端站点源，如 `https://admin.example.com` |
| `Access-Control-Allow-Credentials` | `true` |

不配 CORS 头也能用，但前端只能靠上传完成后回头调 `exists()` 确认结果，拿不到群晖的错误码。

**二、用专用受限账号登录。** 群晖没有 S3 的预签名 URL 机制，签不出「只能写某个路径、几分钟后过期」的凭证。能交给浏览器的只有 `_sid`，它等同该账号在 File Station 的全部权限 —— 拿到的人可以浏览、下载、删除这个账号能碰到的所有文件。唯一的收窄手段是在 DSM 里单独建一个上传专用账号，权限只给目标共享文件夹的写入，驱动配置里用这个账号。做不到按单次操作或单个文件限权。

**三、NAS 要能被浏览器直连，且证书可信。** 当前配置是 `https://10.172.10.87:5001`（内网 IP + 自签证书）：

- 内网 IP 意味着只有办公网内的客户端能直传，外网用户用不了，得保留 `upload()` 这条中转路作为兜底
- 浏览器会拦自签证书。Let's Encrypt 不给 IP 签证书，要么给 NAS 配一个内网 DNS 域名再签正式证书，要么把自签 CA 装进办公机器的信任库
- 前端站点跑 HTTPS 时，不能传给 `http://` 的 NAS 地址，会被混合内容策略拦掉

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

`size` 传了白名单外的值会直接快速失败，不发请求。

**缩略图不是实时生成的。** 群晖的索引服务会预先把缩略图生成好，放在文件同目录的 `@eaDir` 隐藏目录里，Thumb 接口只是去读这个现成的文件。所以没有缩略图时，DSM 不走 JSON 错误码那套，而是直接返回一个 HTTP 404 的 HTML 页面（浏览器里看到的就是「抱歉，您所指定的页面不存在」）。这种情况 `thumbnail()` 会抛出「该文件没有缩略图，需先由索引服务生成」。

视频要有封面图，得同时满足：

- 文件所在的共享文件夹在「控制台 > 索引服务 > 媒体索引」的索引范围内
- 已安装可生成缩略图的套件（Media Server / Synology Photos 之类）
- 索引任务已经跑完 —— 刚上传的大视频要等一会儿，不是立刻就有

### hasThumbnail 探测封面是否可用

`thumbnailUrl()` 只拼地址不发请求，拿不到「封面到底存不存在」这个信息。要在入库前确认就用这个：

```php
if ($nas->hasThumbnail('/home/video/a.mp4')) {
    $poster = $nas->thumbnailUrl('/home/video/a.mp4', ['size' => 'large']);
}
```

内部就是调一次 `thumbnail()` 看会不会抛异常，会真实发一次请求，别在列表循环里对几百个文件逐个调。

### thumbnailUrl 缩略图地址

和 `thumbnail()` 同一个接口，区别是只拼地址、不取二进制，交给浏览器去加载：

```php
$poster = $nas->thumbnailUrl('/home/video/a.mp4', [
    'size'   => 'large', // small|medium|large|original，默认 medium
    'rotate' => 0,
]);
```

主要用途是给 `<video>` 的 `poster` 属性，实现「进页面只加载封面图、点击后才加载视频」：

```php
$poster   = $nas->thumbnailUrl('/home/video/a.mp4', ['size' => 'large']);
$videoUrl = $nas->previewUrl('/home/video/a.mp4');
```

```html
<!-- preload="none" 让浏览器进页面时一个字节的视频都不下载，只显示 poster -->
<video src="<?= $videoUrl ?>" poster="<?= $poster ?>" preload="none" controls></video>
```

`preload="none"` + `poster` 是原生行为，不用写 JS：初始只请求封面图，用户点播放键才开始拉视频。如果想连视频地址都延迟到点击后再从后端取（避免 `_sid` 过早暴露），就先只渲染 `poster`，点击时再请求接口拿 `previewUrl()` 填进 `src`。

这个方法不发请求，所以**不保证封面图真的存在**。视频没被索引时该地址返回 404，`<video>` 的表现是不显示 poster（退化成黑屏或首帧）。要兜底就在前端换成一张 `<img>` 占位、`onerror` 时替换为默认图，或者后端先用 `hasThumbnail()` 探测。

缩略图地址同样内含 `_sid`，安全权衡和 `previewUrl()` 一样。

三条路按场景选：`previewUrl()` 和 `thumbnailUrl()` 不占 PHP 进程，适合直接给前端；`thumbnail()` 拿到二进制，适合自己做缓存层。

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

        // 视频封面图，配合 <video poster> preload="none" 实现点击后才加载
        $poster = $nas->thumbnailUrl('/home/demo/movie.mp4', ['size' => 'large']);

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

`rename` / `read` / `thumbnail` / `thumbnailUrl` / `previewUrl` 在 `Base` 里有默认的「抛不支持」实现，新驱动不实现也能跑。实现了就在 `$abilities` 里把对应开关置为 `true`：

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
        'rename'       => false,  // 该厂商没有重命名接口
        'read'         => true,
        'thumbnail'    => false,
        'previewUrl'   => true,
        'thumbnailUrl' => false,
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

**查询串必须用 `rawurlencode`，不能用 `urlencode`。** `urlencode` 把空格编成 `+`，这是表单编码的规则；放在 URL 查询串里，部分 CGI 会把 `+` 原样当加号处理，于是带空格的 NAS 路径就变成了一个不存在的路径。表现为路径里有空格的文件请求 404，没空格的一切正常。

**缩略图 404 不是错误码而是 HTML 页面。** Thumb 接口读的是索引服务预生成在 `@eaDir` 里的缩略图文件，不存在时 DSM 直接回 404 页面，不走 JSON 错误信封，所以 `detectJsonError()` 那套判错逻辑接不住。`requestContent()` 现在把 HTTP 状态码塞进异常的 code，`thumbnail()` 据此把 404 翻译成可读信息。

**群晖参数里的逗号要转义**，因为逗号是多值分隔符。但两类值不能转义：一是 `passwd` / `password`，按文档要求原样传，否则含逗号的密码会登录失败；二是已经是 JSON 数组形式的值（如 `path`、`name`），其中的逗号是 JSON 语法的一部分。

## 待办

- 视频的服务端 Range 代理（分片转发）还没做。目前视频预览靠 `previewUrl()` 让浏览器直连 NAS，如果要完全隐藏 NAS 地址和凭证，需要额外实现一个按 Range 转发的方法。
- 威联通的 `read` / `thumbnail` / `thumbnailUrl` / `previewUrl` / `uploadTicket` 待真机验证后补齐。
- 直传只适用于能直连 NAS 的网络。外网场景仍需走 `upload()` 中转，业务层得按客户端所处网络二选一。
- 视频封面依赖 NAS 的索引服务，驱动这边没法代劳。如果整个共享文件夹都没开媒体索引，`thumbnail()` / `thumbnailUrl()` 对视频永远拿不到图，这时只能自己用 ffmpeg 抽帧生成封面再传回 NAS。

