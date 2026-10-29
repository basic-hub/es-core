<?php

namespace BasicHub\EsCore\Common\CloudLib\Nas;

/**
 * 威联通 NAS（QTS 系统）文件管理驱动
 * 
 * 说明：Qfinder Pro 实际是威联通的局域网发现工具（通过 UDP 广播发现设备），
 * 本身没有 HTTP API。本类对接的是 QTS 的 Authentication API 和 File Station API。
 * 
 * 配置示例：
 * [
 *     'host'     => 'https://192.168.1.100:8080',  // 必须带协议和端口
 *     'username' => 'admin',
 *     'password' => 'password123',
 *     'otpCode'  => '',  // 两步验证动态码（启用时必填）
 * ]
 * 
 * 文档：
 * - Authentication API: https://download.qnap.com/dev/API_QNAP_QTS_Authentication.pdf
 * - File Station API: https://download.qnap.com/dev/API_QNAP_QTS_File_Station.pdf
 * 
 * @document https://download.qnap.com/dev/API_QNAP_QTS_Authentication.pdf
 * @document https://download.qnap.com/dev/API_QNAP_QTS_File_Station.pdf
 */
class QfinderPro extends Base
{
    /**
     * 认证接口（QTS 5.0.1 及以上）
     */
    const URI_AUTH = '/cgi-bin/authLogin.cgi';

    /**
     * File Station 接口
     */
    const URI_FILE = '/cgi-bin/filemanager/utilRequest.cgi';

    /**
     * 操作成功的 status 值
     */
    const STATUS_OK = 1;

    /**
     * 删除时是否永久删除
     * true  - 直接删除
     * false - 移入回收站
     * @var bool
     */
    protected $forceDelete = false;

    /**
     * 同名冲突策略：skip（跳过）| overwrite（覆盖）| rename（自动改名）
     * @var string
     */
    protected $duplicate = 'overwrite';

    /**
     * File Station 的 status 错误码
     * @document QNAP_QTS_File_Station_API_v5.pdf
     * @var array
     */
    protected static $statusMap = [
        0  => '操作失败',
        1  => '成功',
        2  => '文件已存在',
        3  => '非法的操作',
        4  => '权限被拒绝',
        5  => '文件或目录不存在',
        8  => 'File Manager 服务被禁用',
        9  => '超出配额限制',
        33 => '名称重复',
        64 => '远程 HTTP 登录失败',
    ];

    /**
     * 登录获取 sid
     *
     * pwd 使用 ezEncode 编码（base64），不能传明文。
     * 若开启两步验证，首次响应会带 qtoken，需携带 qtoken + 动态码再次请求。
     * @param bool $force
     * @return string
     * @throws \Exception
     */
    public function login($force = false)
    {
        if (!$force && !empty($this->sessionToken)) {
            return $this->sessionToken;
        }

        $query = [
            'user'       => $this->username,
            'pwd'        => base64_encode($this->password),
            'serviceKey' => 1,
        ];

        $data = $this->requestXml(self::URI_AUTH, $query);

        // 两步验证：拿到 qtoken 后带动态码二次认证
        if (empty($data['authSid']) && !empty($data['qtoken'])) {
            if (empty($this->otpCode)) {
                throw new \Exception('威联通NAS已开启两步验证，请配置 otpCode');
            }
            $data = $this->requestXml(self::URI_AUTH, [
                'user'          => $this->username,
                'qtoken'        => $data['qtoken'],
                'security_code' => $this->otpCode,
                'serviceKey'    => 1,
            ]);
        }

        if (empty($data['authPassed']) || $data['authPassed'] != 1 || empty($data['authSid'])) {
            throw new \Exception('威联通NAS登录失败：' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        $this->sessionToken = $data['authSid'];
        return $this->sessionToken;
    }

    public function logout()
    {
        if (empty($this->sessionToken)) {
            return true;
        }

        try {
            $this->requestXml(self::URI_AUTH, ['logout' => 1, 'sid' => $this->sessionToken]);
        } catch (\Exception $e) {
            trace('威联通NAS注销失败：' . $e->getMessage(), 'warning');
        }

        $this->sessionToken = '';
        return true;
    }

    /**
     * 列出目录内容
     * @document func=get_list
     * @param string $path
     * @param array $options limit|start|sort|dir|hidden_file
     * @return array
     * @throws \Exception
     */
    public function listDir($path, $options = [])
    {
        $path = $this->normalizePath($path);

        $query = [
            'func'        => 'get_list',
            'sid'         => $this->getSessionToken(),
            'path'        => $path,
            'is_iso'      => 0,
            'list_mode'   => 'all',
            'start'       => $options['start'] ?? 0,
            'limit'       => $options['limit'] ?? 1000,
            'sort'        => $options['sort'] ?? 'filename',
            'dir'         => $options['dir'] ?? 'ASC',
            'hidden_file' => $options['hidden_file'] ?? 0,
        ];

        $data = $this->call($query);

        $list = [];
        foreach ($data['datas'] ?? [] as $item) {
            $name = $item['filename'] ?? '';
            $list[] = $this->formatEntry(
                $name,
                $this->normalizePath($path . '/' . $name),
                !empty($item['isfolder']),
                $item['filesize'] ?? 0,
                $item['epochmt'] ?? 0,
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
     *
     * File Station 没有独立的 stat 接口，通过列出父目录后匹配名称实现。
     * @param string $path
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function exists($path, $options = [])
    {
        list($dir, $name) = $this->splitPath($path);
        if ($name === '') {
            return true;
        }

        try {
            $result = $this->listDir($dir, ['limit' => $options['limit'] ?? 100000]);
        } catch (\Exception $e) {
            return false;
        }

        foreach ($result['list'] as $item) {
            if ($item['name'] === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * 创建目录
     * @document func=createdir
     * @param string $path 父目录
     * @param string $name 新目录名
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function mkdir($path, $name, $options = [])
    {
        $this->call([
            'func'        => 'createdir',
            'sid'         => $this->getSessionToken(),
            'dest_path'   => $this->normalizePath($path),
            'dest_folder' => $name,
        ]);
        return true;
    }

    /**
     * 上传文件
     * @document func=upload
     * @param string $file 本地文件绝对路径
     * @param string $path NAS 目标目录
     * @param array $options filename|overwrite
     * @return bool
     * @throws \Exception
     */
    public function upload($file, $path, $options = [])
    {
        if (!is_file($file)) {
            throw new \Exception("本地文件不存在: {$file}");
        }

        $filename = $options['filename'] ?? basename($file);
        $overwrite = isset($options['overwrite']) ? (int)(bool)$options['overwrite'] : 1;

        // 参数均在 URL 上，body 只放 multipart 文件，文件字段名固定为 file
        $query = [
            'func'      => 'upload',
            'sid'       => $this->getSessionToken(),
            'type'      => 'standard',
            'dest_path' => $this->normalizePath($path),
            'overwrite' => $overwrite,
            'progress'  => $filename,
        ];

        $raw = $this->requestUpload(self::URI_FILE, $query, [], 'file', $file, $filename);
        $this->checkStatus($raw);
        return true;
    }

    /**
     * 下载文件
     * @document func=download
     * @param string $path NAS 文件绝对路径
     * @param string $saveFile 本地保存路径
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function download($path, $saveFile, $options = [])
    {
        list($dir, $name) = $this->splitPath($path);

        $query = [
            'func'         => 'download',
            'sid'          => $this->getSessionToken(),
            'isfolder'     => 0,
            'compress'     => 0,
            'source_path'  => $dir,
            'source_file'  => $name,
            'source_total' => 1,
        ];

        return $this->requestDownload(self::URI_FILE, $query, $saveFile);
    }

    /**
     * 删除文件或目录，支持传数组批量删除
     * @document func=delete
     * @param string|array $path
     * @param array $options force
     * @return bool
     * @throws \Exception
     */
    public function delete($path, $options = [])
    {
        list($dir, $names) = $this->groupByDir($path);

        $force = isset($options['force']) ? (int)(bool)$options['force'] : (int)$this->forceDelete;

        $this->call([
            'func'       => 'delete',
            'sid'        => $this->getSessionToken(),
            'path'       => $dir,
            'file_name'  => $names,
            'file_total' => count($names),
            'force'      => $force,
        ]);
        return true;
    }

    /**
     * 复制文件或目录
     * @document func=copy
     * @param string|array $fromPath
     * @param string $toPath 目标目录
     * @param array $options dup
     * @return bool
     * @throws \Exception
     */
    public function copy($fromPath, $toPath, $options = [])
    {
        return $this->copyMove('copy', $fromPath, $toPath, $options);
    }

    /**
     * 移动文件或目录
     * @document func=move
     * @param string|array $fromPath
     * @param string $toPath 目标目录
     * @param array $options dup
     * @return bool
     * @throws \Exception
     */
    public function move($fromPath, $toPath, $options = [])
    {
        return $this->copyMove('move', $fromPath, $toPath, $options);
    }

    /**
     * 重命名（同目录内）
     * @document func=rename
     * @param string $path
     * @param string $newName
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function rename($path, $newName, $options = [])
    {
        list($dir, $name) = $this->splitPath($path);

        $this->call([
            'func'        => 'rename',
            'sid'         => $this->getSessionToken(),
            'path'        => $dir,
            'source_name' => $name,
            'dest_name'   => $newName,
        ]);
        return true;
    }

    /**
     * copy / move 共用实现
     * @param string $func copy|move
     * @param string|array $fromPath
     * @param string $toPath
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    protected function copyMove($func, $fromPath, $toPath, array $options = [])
    {
        list($dir, $names) = $this->groupByDir($fromPath);

        $this->call([
            'func'         => $func,
            'sid'          => $this->getSessionToken(),
            'source_path'  => $dir,
            'source_file'  => $names,
            'source_total' => count($names),
            'dest_path'    => $this->normalizePath($toPath),
            'mode'         => $options['mode'] ?? 1,
            'dup'          => $options['dup'] ?? $this->duplicate,
        ]);
        return true;
    }

    /**
     * 将路径（或路径数组）归组为 [父目录, 文件名数组]
     *
     * File Station 的批量接口要求所有源文件位于同一目录，这里做校验。
     * @param string|array $path
     * @return array
     * @throws \Exception
     */
    protected function groupByDir($path)
    {
        $paths = is_array($path) ? $path : [$path];
        if (empty($paths)) {
            throw new \Exception('路径不能为空');
        }

        $dir = null;
        $names = [];
        foreach ($paths as $item) {
            list($itemDir, $name) = $this->splitPath($item);
            if ($dir !== null && $dir !== $itemDir) {
                throw new \Exception('威联通NAS批量操作要求所有文件位于同一目录');
            }
            $dir = $itemDir;
            $names[] = $name;
        }

        return [$dir, $names];
    }

    /**
     * 调用 File Station 接口并校验 status
     * @param array $query
     * @return array
     * @throws \Exception
     */
    protected function call(array $query)
    {
        $raw = $this->request(self::URI_FILE, $query);
        return $this->checkStatus($raw);
    }

    /**
     * 校验响应体内的 status 字段
     * @param string $raw
     * @return array
     * @throws \Exception
     */
    protected function checkStatus($raw)
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \Exception('威联通NAS响应不是合法JSON：' . substr($raw, 0, 500));
        }

        // 部分接口（如 get_list）成功时不返回 status
        if (!isset($data['status'])) {
            return $data;
        }

        $status = (int)$data['status'];
        if ($status !== self::STATUS_OK) {
            $msg = self::$statusMap[$status] ?? '未知错误';
            throw new \Exception("威联通NAS操作失败[status={$status}]：{$msg}", $status);
        }

        return $data;
    }

    /**
     * 请求认证接口并解析 XML 响应
     * @param string $uri
     * @param array $query
     * @return array
     * @throws \Exception
     */
    protected function requestXml($uri, array $query)
    {
        $raw = $this->request($uri, $query);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_NOCDATA);
        libxml_clear_errors();

        if ($xml === false) {
            throw new \Exception('威联通NAS响应不是合法XML：' . substr($raw, 0, 500));
        }

        $data = json_decode(json_encode($xml), true);
        return is_array($data) ? $data : [];
    }
}
