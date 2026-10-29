<?php

namespace BasicHub\EsCore\Common\CloudLib\Nas;

/**
 * 群晖 Synology DSM Web API 文件管理驱动
 *
 * 配置示例：
 * [
 *     'host'     => 'https://192.168.1.200:5001',  // 必须带协议和端口
 *     'username' => 'admin',
 *     'password' => 'password123',
 *     'otpCode'  => '',     // 两步验证动态码（启用时必填）
 *     'session'  => 'FileStation',  // 会话名称，默认 FileStation
 * ]
 *
 * 文档：
 * - 官方开发者指南：https://global.download.synology.com/download/Document/Software/DeveloperGuide/Firmware/FileStation/All/enu/Synology_File_Station_API_Guide.pdf
 * - API 参考实现：https://github.com/N4S4/synology-api
 *
 * @document https://global.download.synology.com/download/Document/Software/DeveloperGuide/Firmware/FileStation/All/enu/Synology_File_Station_API_Guide.pdf
 */
class Synology extends Base
{
    /**
     * API 版本发现接口，路径固定
     */
    const URI_QUERY = '/webapi/query.cgi';

    /**
     * 会话名，决定该会话凭证的作用范围
     * @var string
     */
    protected $session = 'FileStation';

    /**
     * API 元信息缓存：['SYNO.FileStation.List' => ['path' => '/webapi/entry.cgi', 'version' => 2, 'minVersion' => 1, 'maxVersion' => 2], ...]
     * @var array
     */
    protected $apiInfo = [];

    /**
     * 通用错误码
     * @var array
     */
    protected static $commonErrors = [
        100 => '未知错误',
        101 => '参数无效',
        102 => '请求的 API 不存在',
        103 => '请求的 method 不存在',
        104 => '请求的版本不被支持',
        105 => '当前登录会话权限不足',
        106 => '会话超时',
        107 => '会话被重复登录中断',
        119 => 'SID 无效或 CSRF Token 无效',
    ];

    /**
     * 认证相关错误码
     *
     * 文档仅列出 400~404，406~410 为 DSM 登录接口实际会返回的扩展码，一并翻译便于排查。
     * @var array
     */
    protected static $authErrors = [
        400 => '账号不存在或密码错误',
        401 => '账号被禁用',
        402 => '权限被拒绝',
        403 => '需要两步验证动态码',
        404 => '两步验证动态码校验失败',
        406 => '强制要求开启两步验证',
        407 => 'IP 被封禁',
        408 => '密码已过期且无法修改',
        409 => '密码已过期',
        410 => '密码必须修改',
    ];

    /**
     * File Station 文件操作通用错误码
     * @var array
     */
    protected static $fileErrors = [
        400 => '文件操作参数无效',
        401 => '文件操作未知错误',
        402 => '系统繁忙',
        403 => '执行该文件操作的用户无效',
        404 => '执行该文件操作的用户组无效',
        405 => '执行该文件操作的用户和用户组均无效',
        406 => '无法从账号服务器获取用户或用户组信息',
        407 => '操作不被允许',
        408 => '文件或目录不存在',
        409 => '不支持的文件系统',
        410 => '连接网络文件系统失败（如 CIFS）',
        411 => '只读文件系统',
        412 => '非加密文件系统中的文件名过长',
        413 => '加密文件系统中的文件名过长',
        414 => '文件已存在',
        415 => '超出磁盘配额',
        416 => '设备空间不足',
        417 => '输入或输出错误',
        418 => '非法的名称或路径',
        419 => '非法的文件名',
        420 => 'FAT 文件系统中的非法文件名',
        421 => '设备或资源正忙',
        599 => '不存在该文件操作任务',
    ];

    /**
     * 各 File Station 子接口的专有错误码，与通用错误码不重叠
     * @var array
     */
    protected static $apiErrors = [
        900  => '删除文件或目录失败',
        1000 => '复制文件或目录失败',
        1001 => '移动文件或目录失败',
        1002 => '目标位置发生错误',
        1003 => '未指定 overwrite 参数，无法覆盖或跳过已存在的文件',
        1004 => '同名的文件与目录之间不能相互覆盖',
        1006 => '含特殊字符的文件或目录无法复制或移动到 FAT32 文件系统',
        1007 => '大于 4G 的文件无法复制或移动到 FAT32 文件系统',
        1100 => '创建目录失败',
        1101 => '父目录下的子目录数量将超出系统限制',
        1200 => '重命名失败',
        1800 => 'HTTP 头缺少 Content-Length，或实际接收大小与其不一致',
        1801 => '等待客户端数据超时（默认最长 3600 秒）',
        1802 => '文件内容分段中缺少 filename 信息',
        1803 => '上传连接已被取消',
        1804 => '向 FAT 文件系统上传超大文件失败',
        1805 => '未指定 overwrite 参数，无法覆盖或跳过已存在的文件',
    ];

    /**
     * 各接口在官方文档中已定义的最高版本
     *
     * SYNO.API.Info 返回的 maxVersion 可能高于文档已定义的版本（例如 DSM7 的 Auth 为 7），
     * 高版本的参数语义未必一致，故取文档上限与 maxVersion 的较小值。
     * @var array
     */
    protected static $apiMaxVersions = [
        'SYNO.API.Auth'                 => 3,
        'SYNO.FileStation.List'         => 2,
        'SYNO.FileStation.Upload'       => 3,
        'SYNO.FileStation.Download'     => 2,
        'SYNO.FileStation.CreateFolder' => 2,
        'SYNO.FileStation.Rename'       => 2,
        'SYNO.FileStation.CopyMove'     => 3,
        'SYNO.FileStation.Delete'       => 2,
        'SYNO.FileStation.Thumb'        => 2,
    ];

    /**
     * 缩略图可选尺寸档位
     * @var array
     */
    protected static $thumbSizes = ['small', 'medium', 'large', 'original'];

    /**
     * 群晖的 File Station API 覆盖了全部能力
     * @var array
     */
    protected $abilities = [
        'listDir'    => true,
        'exists'     => true,
        'mkdir'      => true,
        'upload'     => true,
        'download'   => true,
        'delete'     => true,
        'copy'       => true,
        'move'       => true,
        'rename'     => true,
        'read'         => true,
        'thumbnail'    => true,
        'previewUrl'   => true,
        'thumbnailUrl' => true,
        'uploadTicket' => true,
    ];

    /**
     * 登录获取 sid
     *
     * 不启用 enable_syno_token，避免每个请求都要带 X-SYNO-TOKEN，
     * 纯服务端调用不存在 CSRF 风险。
     * @param bool $force
     * @return string
     * @throws \Exception
     */
    public function login($force = false)
    {
        if (!$force && !empty($this->sessionToken)) {
            return $this->sessionToken;
        }

        $info = $this->getApiInfo('SYNO.API.Auth');

        $query = [
            'api'     => 'SYNO.API.Auth',
            'version' => $info['version'],
            'method'  => 'login',
            'account' => $this->username,
            // passwd 不做转义，按文档要求原样传
            'passwd'  => $this->password,
            'session' => $this->session,
            'format'  => 'sid',
        ];

        if (!empty($this->otpCode)) {
            $query['otp_code'] = $this->otpCode;
        }

        $data = $this->call($info['path'], $query, 'auth');

        if (empty($data['sid'])) {
            throw new \Exception('群晖NAS登录失败：响应中未返回 sid');
        }

        $this->sessionToken = $data['sid'];
        return $this->sessionToken;
    }

    public function logout()
    {
        if (empty($this->sessionToken)) {
            return true;
        }

        try {
            $info = $this->getApiInfo('SYNO.API.Auth');
            $this->call($info['path'], [
                'api'     => 'SYNO.API.Auth',
                'version' => $info['version'],
                'method'  => 'logout',
                'session' => $this->session,
                '_sid'    => $this->sessionToken,
            ], 'auth');
        } catch (\Exception $e) {
            trace('群晖NAS注销失败：' . $e->getMessage(), 'warning');
        }

        $this->sessionToken = '';
        return true;
    }

    /**
     * 列出目录内容
     *
     * path 传 / 时自动改用 list_share 列出共享文件夹（群晖的根目录即共享文件夹列表）。
     * @param string $path
     * @param array $options offset|limit|sort_by|sort_direction|onlywritable|filetype|pattern
     * @return array
     * @throws \Exception
     */
    public function listDir($path, $options = [])
    {
        $path = $this->normalizePath($path);
        $info = $this->getApiInfo('SYNO.FileStation.List');

        $query = [
            'api'     => 'SYNO.FileStation.List',
            'version' => $info['version'],
        ];

        // 根目录与子目录使用不同方法，可用的 additional 选项也不同
        if ($path === '/') {
            $query['method'] = 'list_share';
            $query['onlywritable'] = !empty($options['onlywritable']);
            $additional = ['real_path', 'size', 'owner', 'time', 'perm', 'mount_point_type', 'volume_status'];
        } else {
            $query['method'] = 'list';
            $query['folder_path'] = $path;
            $query['filetype'] = $options['filetype'] ?? 'all';
            if (!empty($options['pattern'])) {
                $query['pattern'] = $options['pattern'];
            }
            $additional = ['real_path', 'size', 'owner', 'time', 'perm', 'mount_point_type', 'type'];
        }

        // 追加通用参数，limit 为 0 表示返回全部
        $query['offset'] = (int)($options['offset'] ?? 0);
        $query['limit'] = (int)($options['limit'] ?? 0);
        $query['sort_by'] = $options['sort_by'] ?? 'name';
        $query['sort_direction'] = $options['sort_direction'] ?? 'asc';
        $query['additional'] = json_encode($additional);
        $query['_sid'] = $this->getSessionToken();

        $data = $this->call($info['path'], $query, 'file');

        $items = $path === '/' ? ($data['shares'] ?? []) : ($data['files'] ?? []);

        $list = [];
        foreach ($items as $item) {
            $additional = $item['additional'] ?? [];
            $time = $additional['time'] ?? [];
            $list[] = $this->formatEntry(
                $item['name'] ?? '',
                $item['path'] ?? '',
                !empty($item['isdir']),
                $additional['size'] ?? 0,
                $time['mtime'] ?? 0,
                $item
            );
        }

        return [
            'total' => $data['total'] ?? count($list),
            'list'  => $list,
        ];
    }

    /**
     * 判断文件或目录是否存在
     * @document SYNO.FileStation.List method=getinfo
     * @param string $path
     * @param array $options
     * @return bool
     */
    public function exists($path, $options = [])
    {
        $info = $this->getApiInfo('SYNO.FileStation.List');

        try {
            $data = $this->call($info['path'], [
                'api'        => 'SYNO.FileStation.List',
                'version'    => $info['version'],
                'method'     => 'getinfo',
                'path'       => json_encode([$this->normalizePath($path)]),
                'additional' => json_encode(['real_path']),
                '_sid'       => $this->getSessionToken(),
            ], 'file');
        } catch (\Exception $e) {
            return false;
        }

        $files = $data['files'] ?? [];
        if (empty($files)) {
            return false;
        }

        // 路径不存在时群晖仍返回该条目，但会带 code 标记错误
        return empty($files[0]['code']);
    }

    /**
     * 创建目录
     * @document SYNO.FileStation.CreateFolder
     * @param string $path 父目录
     * @param string $name 新目录名
     * @param array $options force_parent
     * @return bool
     * @throws \Exception
     */
    public function mkdir($path, $name, $options = [])
    {
        $info = $this->getApiInfo('SYNO.FileStation.CreateFolder');

        $this->call($info['path'], [
            'api'          => 'SYNO.FileStation.CreateFolder',
            'version'      => $info['version'],
            'method'       => 'create',
            'folder_path'  => json_encode([$this->normalizePath($path)]),
            'name'         => json_encode([$name]),
            'force_parent' => !empty($options['force_parent']),
            '_sid'         => $this->getSessionToken(),
        ], 'file');
        return true;
    }

    /**
     * 上传文件
     *
     * 按 RFC 1867 以 multipart/form-data 提交，各参数独立成段，
     * 二进制文件段必须位于最后，段名固定为 file；
     * api/version/method/_sid 走 URL 查询串。
     * @document SYNO.FileStation.Upload
     * @param string $file 本地文件绝对路径
     * @param string $path NAS 目标目录
     * @param array $options filename|overwrite|create_parents|mtime|crtime|atime
     * @return bool
     * @throws \Exception
     */
    public function upload($file, $path, $options = [])
    {
        if (!is_file($file)) {
            throw new \Exception("本地文件不存在: {$file}");
        }

        $info = $this->getApiInfo('SYNO.FileStation.Upload');
        $filename = $options['filename'] ?? basename($file);

        // api/version/method 必须走 URL，DSM 的 entry.cgi 仅从查询串解析路由参数，
        // 放进 multipart 段会被判为缺少参数(101)
        $query = [
            'api'     => 'SYNO.FileStation.Upload',
            'version' => $info['version'],
            'method'  => 'upload',
            '_sid'    => $this->getSessionToken(),
        ];

        // 业务参数作为 multipart 字段提交，二进制文件段位于最后
        $fields = [
            'path'           => $this->normalizePath($path),
            'create_parents' => !empty($options['create_parents']) ? 'true' : 'false',
            'overwrite'      => $this->uploadOverwrite($options['overwrite'] ?? true, $info['version']),
        ];

        // mtime/crtime/atime 单位为毫秒级时间戳，未指定时交由 NAS 自行决定
        foreach (['mtime', 'crtime', 'atime'] as $key) {
            if (isset($options[$key])) {
                $fields[$key] = (string)$options[$key];
            }
        }

        $raw = $this->requestUpload($info['path'], $query, $fields, 'file', $file, $filename);
        $this->parse($raw, 'file');
        return true;
    }

    /**
     * 签发浏览器直传所需的上传地址与表单字段
     *
     * 群晖没有 S3 那种预签名 URL 机制，能下发给浏览器的只有 _sid，
     * 它等同于该账号在 File Station 的全部权限，所以务必用一个
     * 权限只限定到目标共享文件夹的专用 DSM 账号来登录。
     *
     * 返回的结构交给前端后，前端按 fields 顺序塞进 FormData，
     * 文件段放最后（段名取 file_field），POST 到 url 即可。
     * @document SYNO.FileStation.Upload
     * @param string $path NAS 目标目录
     * @param array $options overwrite|create_parents|mtime|crtime|atime
     * @return array
     * @throws \Exception
     */
    public function uploadTicket($path, $options = [])
    {
        $info = $this->getApiInfo('SYNO.FileStation.Upload');

        // api/version/method/_sid 必须在查询串里，entry.cgi 只从那里解析路由参数
        $query = [
            'api'     => 'SYNO.FileStation.Upload',
            'version' => $info['version'],
            'method'  => 'upload',
            '_sid'    => $this->getSessionToken(),
        ];

        // 业务参数必须作为 multipart 字段提交，且都要排在文件段之前
        $fields = [
            'path'           => $this->normalizePath($path),
            'create_parents' => !empty($options['create_parents']) ? 'true' : 'false',
            'overwrite'      => $this->uploadOverwrite($options['overwrite'] ?? true, $info['version']),
        ];

        foreach (['mtime', 'crtime', 'atime'] as $key) {
            if (isset($options[$key])) {
                $fields[$key] = (string)$options[$key];
            }
        }

        return [
            'url'        => $this->buildUrl($info['path'], $query),
            'method'     => 'POST',
            'file_field' => 'file',
            'fields'     => $this->escape($fields),
        ];
    }

    /**
     * 归一化上传的 overwrite 参数
     *
     * version 2 取 true/false，version 3 起取 overwrite/skip，语义一一对应。
     * @param bool|string $overwrite
     * @param int $version
     * @return string
     */
    protected function uploadOverwrite($overwrite, $version)
    {
        if (is_bool($overwrite)) {
            if ($version >= 3) {
                return $overwrite ? 'overwrite' : 'skip';
            }
            return $overwrite ? 'true' : 'false';
        }
        return (string)$overwrite;
    }

    /**
     * 下载文件
     * @document SYNO.FileStation.Download
     * @param string $path NAS 文件绝对路径
     * @param string $saveFile 本地保存路径
     * @param array $options mode
     * @return bool
     * @throws \Exception
     */
    public function download($path, $saveFile, $options = [])
    {
        $info = $this->getApiInfo('SYNO.FileStation.Download');

        $query = [
            'api'     => 'SYNO.FileStation.Download',
            'version' => $info['version'],
            'method'  => 'download',
            'path'    => json_encode([$this->normalizePath($path)]),
            'mode'    => $options['mode'] ?? 'download',
            '_sid'    => $this->getSessionToken(),
        ];

        return $this->requestDownload($info['path'], $query, $saveFile);
    }

    /**
     * 读取文件原始内容
     *
     * 内容会完整进内存，读取前先用 getinfo 预检大小；事后再判断已经晚了。
     * @document SYNO.FileStation.Download mode=open
     * @param string $path NAS 文件绝对路径
     * @param array $options maxSize 允许的最大字节数
     * @return string
     * @throws \Exception
     */
    public function read($path, $options = [])
    {
        $path = $this->normalizePath($path);
        $maxSize = (int)($options['maxSize'] ?? $this->maxReadSize);

        $meta = $this->getFileInfo($path);
        if (!empty($meta['isdir'])) {
            throw new \Exception("目标是目录，不能读取内容: {$path}");
        }

        $size = (int)($meta['additional']['size'] ?? 0);
        if ($maxSize > 0 && $size > $maxSize) {
            throw new \Exception(sprintf(
                '文件过大无法直接读取（%d 字节，上限 %d 字节），请改用 download(): %s',
                $size, $maxSize, $path
            ));
        }

        $info = $this->getApiInfo('SYNO.FileStation.Download');

        $query = [
            'api'     => 'SYNO.FileStation.Download',
            'version' => $info['version'],
            'method'  => 'download',
            'path'    => json_encode([$path]),
            'mode'    => 'open',
            '_sid'    => $this->getSessionToken(),
        ];

        list(, $headers, $body) = $this->requestContent($info['path'], $this->escape($query));

        // 失败时 DSM 同样回 200，只是响应体换成 JSON 错误信封
        if ($this->detectJsonError($headers['content-type'] ?? '', $body) !== null) {
            $this->parse($body, 'file');
        }

        return $body;
    }

    /**
     * 获取图片/视频的缩略图
     *
     * 缩略图不是实时生成的，而是由索引服务预先生成后存在同目录的 @eaDir 里。
     * 没有现成缩略图时 DSM 不返回 JSON 错误码，而是直接回 HTTP 404 页面。
     * @document SYNO.FileStation.Thumb
     * @param string $path NAS 文件绝对路径
     * @param array $options size 尺寸档位 small|medium|large|original、rotate 旋转档位 0-4
     * @return string 缩略图二进制内容
     * @throws \Exception
     */
    public function thumbnail($path, $options = [])
    {
        $info  = $this->getApiInfo('SYNO.FileStation.Thumb');
        $query = $this->thumbQuery($info['version'], $path, $options);

        try {
            list(, $headers, $body) = $this->requestContent($info['path'], $this->escape($query));
        } catch (\Exception $e) {
            if ((int)$e->getCode() === 404) {
                throw new \Exception("该文件没有缩略图，需先由索引服务生成: {$path}", 404, $e);
            }
            throw $e;
        }

        if ($this->detectJsonError($headers['content-type'] ?? '', $body) !== null) {
            $this->parse($body, 'file');
        }

        if ($body === '') {
            throw new \Exception("缩略图为空，该文件可能尚未被索引: {$path}");
        }

        return $body;
    }

    /**
     * 判断指定文件当前是否已有可用缩略图
     *
     * thumbnailUrl() 只拼地址、不发请求，无法得知封面图是否真的存在。
     * 需要在入库前确认封面可用时用这个方法探测一次。
     * @param string $path NAS 文件绝对路径
     * @param array $options 同 thumbnail()
     * @return bool
     */
    public function hasThumbnail($path, $options = [])
    {
        try {
            $this->thumbnail($path, $options);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 生成可直接内嵌 img/video 标签的预览地址
     *
     * mode=open 让 NAS 内联出流，视频拖动进度依赖 DSM 对 Range 请求的支持；
     * 地址内含 _sid，等同于当前账号的文件访问权限，不要长期缓存或外发。
     * @document SYNO.FileStation.Download mode=open
     * @param string $path NAS 文件绝对路径
     * @param array $options mode 可改为 download 强制触发浏览器下载
     * @return string
     * @throws \Exception
     */
    public function previewUrl($path, $options = [])
    {
        $info = $this->getApiInfo('SYNO.FileStation.Download');

        $query = [
            'api'     => 'SYNO.FileStation.Download',
            'version' => $info['version'],
            'method'  => 'download',
            'path'    => json_encode([$this->normalizePath($path)]),
            'mode'    => $options['mode'] ?? 'open',
            '_sid'    => $this->getSessionToken(),
        ];

        return $this->buildUrl($info['path'], $this->escape($query));
    }

    /**
     * 生成缩略图地址，可直接作为 video 标签的 poster
     *
     * 与 thumbnail() 走的是同一个接口，区别是这里只拼地址、不取二进制，
     * 适合交给浏览器去加载。
     *
     * 注意：只拼地址不发请求，所以不保证封面图真的存在 —— 视频需先被
     * 索引服务生成缩略图，否则该地址会返回 404。要先确认可用性就调
     * hasThumbnail()，前端兜底则给 poster 配 onerror 占位图。
     * 地址内含 _sid，安全权衡与 previewUrl() 一致。
     * @document SYNO.FileStation.Thumb
     * @param string $path NAS 文件绝对路径
     * @param array $options size 尺寸档位 small|medium|large|original、rotate 旋转档位 0-4
     * @return string
     * @throws \Exception
     */
    public function thumbnailUrl($path, $options = [])
    {
        $info = $this->getApiInfo('SYNO.FileStation.Thumb');

        return $this->buildUrl($info['path'], $this->escape($this->thumbQuery($info['version'], $path, $options)));
    }

    /**
     * 拼装缩略图接口的请求参数，thumbnail() 与 thumbnailUrl() 共用
     * @param int|string $version
     * @param string $path
     * @param array $options
     * @return array
     * @throws \Exception
     */
    protected function thumbQuery($version, $path, array $options)
    {
        $size = $options['size'] ?? 'medium';
        if (!in_array($size, self::$thumbSizes, true)) {
            throw new \Exception('缩略图尺寸无效，可选: ' . implode('|', self::$thumbSizes));
        }

        return [
            'api'     => 'SYNO.FileStation.Thumb',
            'version' => $version,
            'method'  => 'get',
            'path'    => $this->normalizePath($path),
            'size'    => $size,
            'rotate'  => (int)($options['rotate'] ?? 0),
            '_sid'    => $this->getSessionToken(),
        ];
    }

    /**
     * 取单个文件或目录的元信息
     * @document SYNO.FileStation.List method=getinfo
     * @param string $path
     * @return array
     * @throws \Exception
     */
    protected function getFileInfo($path)
    {
        $path = $this->normalizePath($path);
        $info = $this->getApiInfo('SYNO.FileStation.List');

        $data = $this->call($info['path'], [
            'api'        => 'SYNO.FileStation.List',
            'version'    => $info['version'],
            'method'     => 'getinfo',
            'path'       => json_encode([$path]),
            'additional' => json_encode(['real_path', 'size', 'time', 'type']),
            '_sid'       => $this->getSessionToken(),
        ], 'file');

        $file = $data['files'][0] ?? [];
        // 路径不存在时群晖仍返回该条目，但会带 code 标记错误
        if (empty($file) || !empty($file['code'])) {
            throw new \Exception("文件不存在: {$path}");
        }

        return $file;
    }

    /**
     * 删除文件或目录（异步任务，内部已轮询至完成）
     * @document SYNO.FileStation.Delete
     * @param string|array $path
     * @param array $options recursive
     * @return bool
     * @throws \Exception
     */
    public function delete($path, $options = [])
    {
        $info = $this->getApiInfo('SYNO.FileStation.Delete');

        $data = $this->call($info['path'], [
            'api'               => 'SYNO.FileStation.Delete',
            'version'           => $info['version'],
            'method'            => 'start',
            'path'              => json_encode($this->toPathList($path)),
            'recursive'         => !isset($options['recursive']) || !empty($options['recursive']),
            'accurate_progress' => true,
            '_sid'              => $this->getSessionToken(),
        ], 'file');

        if (empty($data['taskid'])) {
            throw new \Exception('群晖NAS删除任务创建失败');
        }

        return $this->waitTask('SYNO.FileStation.Delete', $data['taskid']);
    }

    /**
     * 复制文件或目录（异步任务）
     * @document SYNO.FileStation.CopyMove
     * @param string|array $fromPath
     * @param string $toPath 目标目录
     * @param array $options overwrite
     * @return bool
     * @throws \Exception
     */
    public function copy($fromPath, $toPath, $options = [])
    {
        return $this->copyMove($fromPath, $toPath, false, $options);
    }

    /**
     * 移动文件或目录（异步任务）
     * @document SYNO.FileStation.CopyMove
     * @param string|array $fromPath
     * @param string $toPath 目标目录
     * @param array $options overwrite
     * @return bool
     * @throws \Exception
     */
    public function move($fromPath, $toPath, $options = [])
    {
        return $this->copyMove($fromPath, $toPath, true, $options);
    }

    /**
     * 重命名（同步接口）
     * @document SYNO.FileStation.Rename
     * @param string $path
     * @param string $newName
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function rename($path, $newName, $options = [])
    {
        $info = $this->getApiInfo('SYNO.FileStation.Rename');

        $this->call($info['path'], [
            'api'        => 'SYNO.FileStation.Rename',
            'version'    => $info['version'],
            'method'     => 'rename',
            'path'       => json_encode([$this->normalizePath($path)]),
            'name'       => json_encode([$newName]),
            'additional' => json_encode(['real_path']),
            '_sid'       => $this->getSessionToken(),
        ], 'file');
        return true;
    }

    /**
     * copy / move 共用实现，区别仅在 remove_src 参数
     * @param string|array $fromPath
     * @param string $toPath
     * @param bool $removeSrc true 为移动，false 为复制
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    protected function copyMove($fromPath, $toPath, $removeSrc, array $options = [])
    {
        $info = $this->getApiInfo('SYNO.FileStation.CopyMove');

        // 不传 overwrite 时群晖遇同名文件会直接报错（1003），故默认显式传 false 跳过
        $overwrite = $options['overwrite'] ?? false;
        if (is_bool($overwrite)) {
            $overwrite = $overwrite ? 'true' : 'false';
        }

        $data = $this->call($info['path'], [
            'api'               => 'SYNO.FileStation.CopyMove',
            'version'           => $info['version'],
            'method'            => 'start',
            'path'              => json_encode($this->toPathList($fromPath)),
            'dest_folder_path'  => $this->normalizePath($toPath),
            'overwrite'         => $overwrite,
            'remove_src'        => $removeSrc ? 'true' : 'false',
            'accurate_progress' => true,
            '_sid'              => $this->getSessionToken(),
        ], 'file');

        if (empty($data['taskid'])) {
            throw new \Exception('群晖NAS复制/移动任务创建失败');
        }

        return $this->waitTask('SYNO.FileStation.CopyMove', $data['taskid']);
    }

    /**
     * 轮询异步任务直至完成
     * @param string $api
     * @param string $taskid
     * @return bool
     * @throws \Exception
     */
    protected function waitTask($api, $taskid)
    {
        $info = $this->getApiInfo($api);

        for ($i = 0; $i < $this->taskMaxTimes; $i++) {
            $this->sleep($this->taskInterval);

            $data = $this->call($info['path'], [
                'api'     => $api,
                'version' => $info['version'],
                'method'  => 'status',
                'taskid'  => $taskid,
                '_sid'    => $this->getSessionToken(),
            ], 'file');

            if (!empty($data['finished'])) {
                // 任务级错误不会体现在 success 字段上，需单独检查
                if (!empty($data['has_fail'])) {
                    throw new \Exception('群晖NAS任务执行失败：' . json_encode($data, JSON_UNESCAPED_UNICODE));
                }
                return true;
            }
        }

        throw new \Exception("群晖NAS任务轮询超时：api={$api} taskid={$taskid}");
    }

    /**
     * 将路径（或路径数组）规范化为路径数组
     * @param string|array $path
     * @return array
     * @throws \Exception
     */
    protected function toPathList($path)
    {
        $paths = is_array($path) ? $path : [$path];
        if (empty($paths)) {
            throw new \Exception('路径不能为空');
        }

        $result = [];
        foreach ($paths as $item) {
            $result[] = $this->normalizePath($item);
        }
        return $result;
    }

    /**
     * 获取 API 的真实路径与可用版本
     *
     * DSM6 与 DSM7 的 path/version 差异很大（如 FileStation/file_share.cgi -> entry.cgi），
     * 必须通过 SYNO.API.Info 动态发现，不能硬编码。
     * @param string $api
     * @return array ['path' => '/webapi/entry.cgi', 'version' => 2, 'minVersion' => 1, 'maxVersion' => 3]
     * @throws \Exception
     */
    protected function getApiInfo($api)
    {
        if (isset($this->apiInfo[$api])) {
            return $this->apiInfo[$api];
        }

        $raw = $this->request(self::URI_QUERY, [
            'api'     => 'SYNO.API.Info',
            'version' => 1,
            'method'  => 'query',
            'query'   => 'all',
        ]);

        $data = $this->parse($raw, 'common');

        foreach ($data as $name => $item) {
            if (!isset($item['path'], $item['maxVersion'])) {
                continue;
            }

            $minVersion = (int)($item['minVersion'] ?? 1);
            $maxVersion = (int)$item['maxVersion'];

            // 取文档已定义版本与设备支持版本的较小值，并保证不低于设备的 minVersion
            $version = $maxVersion;
            if (isset(self::$apiMaxVersions[$name])) {
                $version = min($maxVersion, self::$apiMaxVersions[$name]);
            }
            if ($version < $minVersion) {
                $version = $minVersion;
            }

            $this->apiInfo[$name] = [
                'path'       => '/webapi/' . ltrim($item['path'], '/'),
                'version'    => $version,
                'minVersion' => $minVersion,
                'maxVersion' => $maxVersion,
            ];
        }

        if (!isset($this->apiInfo[$api])) {
            throw new \Exception("群晖NAS不支持该API：{$api}，请确认已安装 File Station 套件");
        }

        return $this->apiInfo[$api];
    }

    /**
     * 调用接口并解析响应
     * @param string $uri 由 getApiInfo 得到的真实路径
     * @param array $query
     * @param string $scope common|auth|file 决定错误码翻译表
     * @return array
     * @throws \Exception
     */
    protected function call($uri, array $query, $scope = 'common')
    {
        $raw = $this->request($uri, $this->escape($query));
        return $this->parse($raw, $scope);
    }

    /**
     * 解析群晖统一响应结构
     * @param string $raw
     * @param string $scope
     * @return array
     * @throws \Exception
     */
    protected function parse($raw, $scope = 'common')
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \Exception('群晖NAS响应不是合法JSON：' . substr($raw, 0, 500));
        }

        if (empty($data['success'])) {
            $code = (int)($data['error']['code'] ?? 0);
            $message = "群晖NAS操作失败[code={$code}]：" . $this->errorMsg($code, $scope);

            // errors 为逐个文件的明细错误，定位具体失败路径时必需
            $detail = $this->errorDetail($data['error']['errors'] ?? [], $scope);
            if ($detail !== '') {
                $message .= '（' . $detail . '）';
            }

            throw new \Exception($message, $code);
        }

        return $data['data'] ?? [];
    }

    /**
     * 拼接 error.errors 中的明细错误
     * @param mixed $errors
     * @param string $scope
     * @return string
     */
    protected function errorDetail($errors, $scope)
    {
        if (!is_array($errors) || empty($errors)) {
            return '';
        }

        $items = [];
        foreach ($errors as $error) {
            if (!is_array($error)) {
                continue;
            }

            $subCode = (int)($error['code'] ?? 0);
            $text = $subCode > 0 ? "code={$subCode} " . $this->errorMsg($subCode, $scope) : '';

            // 明细中可能带 path 或 name，指明具体出错的对象
            foreach (['path', 'name'] as $key) {
                if (!empty($error[$key]) && is_string($error[$key])) {
                    $text = $error[$key] . ': ' . $text;
                    break;
                }
            }

            if ($text !== '') {
                $items[] = $text;
            }
        }

        return implode('; ', $items);
    }

    /**
     * 错误码翻译
     * @param int $code
     * @param string $scope
     * @return string
     */
    protected function errorMsg($code, $scope)
    {
        if (isset(self::$commonErrors[$code])) {
            return self::$commonErrors[$code];
        }
        if ($scope === 'auth' && isset(self::$authErrors[$code])) {
            return self::$authErrors[$code];
        }
        if ($scope === 'file' && isset(self::$fileErrors[$code])) {
            return self::$fileErrors[$code];
        }
        if (isset(self::$apiErrors[$code])) {
            return self::$apiErrors[$code];
        }
        return '未知错误';
    }

    /**
     * 群晖参数转义：逗号用于分隔多值，须转义；反斜线同样须转义
     *
     * 两类值不转义：
     * 1. passwd / password，按文档要求原样传，否则含逗号的密码会登录失败
     * 2. 已经是 JSON 数组形式的值（如 path、name），其中的逗号是 JSON 语法的一部分
     * @param array $query
     * @return array
     */
    protected function escape(array $query)
    {
        foreach ($query as $key => $value) {
            if ($key === 'passwd' || $key === 'password' || !is_string($value)) {
                continue;
            }
            if (strlen($value) > 1 && $value[0] === '[' && substr($value, -1) === ']') {
                continue;
            }
            $query[$key] = str_replace(['\\', ','], ['\\\\', '\\,'], $value);
        }
        return $query;
    }
}
