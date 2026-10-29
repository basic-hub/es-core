<?php

namespace BasicHub\EsCore\Common\CloudLib\Nas;

/**
 * NAS 文件管理统一接口
 *
 * 与 Storage（对象存储）的区别：
 * 1. 对象存储是扁平 key，NAS 是真实的目录树，需要 mkdir、list 等目录语义
 * 2. 对象存储用 AK/SK 无状态签名，NAS 基本都是"登录拿 session -> 带 session 操作"
 * 3. 各厂商 API 能力差异很大（如 TrueNAS 没有删除/移动接口），调用方需按 can() 降级
 */
interface NasInterface
{
    /**
     * 登录并获取会话凭证，重复调用直接复用已有会话
     * @param bool $force 是否强制重新登录
     * @return string 会话凭证 sessionToken
     */
    public function login($force = false);

    /**
     * 注销会话
     * @return bool
     */
    public function logout();

    /**
     * 列出目录下的文件与子目录
     * @param string $path NAS 绝对路径，如 /Public/doc
     * @param array $options 厂商扩展参数，如 limit、start、sort
     * @return array 标准化结构，见 Base::formatEntry()
     */
    public function listDir($path, $options = []);

    /**
     * 判断文件或目录是否存在
     * @param string $path NAS 绝对路径
     * @param array $options
     * @return bool
     */
    public function exists($path, $options = []);

    /**
     * 创建目录
     * @param string $path 父目录绝对路径
     * @param string $name 新目录名
     * @param array $options
     * @return bool
     */
    public function mkdir($path, $name, $options = []);

    /**
     * 上传本地文件至 NAS
     * @param string $file 本地文件绝对路径
     * @param string $path NAS 目标目录绝对路径
     * @param array $options 厂商扩展参数，如 overwrite、filename
     * @return bool
     */
    public function upload($file, $path, $options = []);

    /**
     * 签发一份可供浏览器直传的上传凭据
     *
     * 返回上传地址与需要一起提交的表单字段，前端用 FormData 直接把文件
     * POST 给 NAS，不再经服务器中转。适用于大文件。
     *
     * 注意：凭据内含会话凭证，等同于当前账号的文件访问权限，只应下发给
     * 可信网络内的可信客户端。
     * @param string $path NAS 目标目录绝对路径
     * @param array $options 厂商扩展参数，如 overwrite、create_parents
     * @return array url、method、file_field、fields
     */
    public function uploadTicket($path, $options = []);

    /**
     * 下载 NAS 文件至本地
     * @param string $path NAS 文件绝对路径
     * @param string $saveFile 本地保存绝对路径
     * @param array $options
     * @return bool
     */
    public function download($path, $saveFile, $options = []);

    /**
     * 读取 NAS 文件的原始内容
     *
     * 内容会完整载入内存，仅适用于小文件（文本、JSON、配置等）；
     * 大文件请改用 download() 落盘，或用 previewUrl() 交给浏览器直连。
     * @param string $path NAS 文件绝对路径
     * @param array $options maxSize 允许的最大字节数，超出直接抛异常
     * @return string 文件原始内容
     */
    public function read($path, $options = []);

    /**
     * 获取图片/视频的缩略图二进制内容
     *
     * 依赖 NAS 自身的索引服务，未被索引或不支持的格式会抛异常。
     * @param string $path NAS 文件绝对路径
     * @param array $options size 尺寸档位、rotate 旋转角度
     * @return string 缩略图二进制内容（通常为 JPEG）
     */
    public function thumbnail($path, $options = []);

    /**
     * 生成可直接内嵌到 img/video 标签的预览地址
     *
     * 由 NAS 直接向浏览器出流，不经过 PHP 转发，视频的拖动进度依赖
     * NAS 对 Range 请求的支持。
     *
     * 注意：返回的地址内含会话凭证，等同于当前账号的文件访问权限，
     * 不应长期缓存或下发给不可信的客户端。
     * @param string $path NAS 文件绝对路径
     * @param array $options 厂商扩展参数
     * @return string 完整的 HTTP 地址
     */
    public function previewUrl($path, $options = []);

    /**
     * 生成缩略图地址，可直接作为 video 标签的 poster 或 img 的 src
     *
     * 与 thumbnail() 的区别是只返回地址、由浏览器去加载，不经 PHP 转发。
     * 同样依赖 NAS 的索引服务，安全权衡与 previewUrl() 一致。
     * @param string $path NAS 文件绝对路径
     * @param array $options size 尺寸档位、rotate 旋转角度
     * @return string 完整的 HTTP 地址
     */
    public function thumbnailUrl($path, $options = []);

    /**
     * 删除文件或目录
     * @param string|array $path NAS 绝对路径，部分厂商支持数组批量
     * @param array $options
     * @return bool
     */
    public function delete($path, $options = []);

    /**
     * 复制文件或目录
     * @param string|array $fromPath 源绝对路径
     * @param string $toPath 目标目录绝对路径
     * @param array $options
     * @return bool
     */
    public function copy($fromPath, $toPath, $options = []);

    /**
     * 移动文件或目录
     * @param string|array $fromPath 源绝对路径
     * @param string $toPath 目标目录绝对路径
     * @param array $options
     * @return bool
     */
    public function move($fromPath, $toPath, $options = []);

    /**
     * 重命名文件或目录（同目录内）
     * @param string $path 源文件绝对路径
     * @param string $newName 新名称（不含路径）
     * @param array $options
     * @return bool
     */
    public function rename($path, $newName, $options = []);

    /**
     * 查询当前驱动是否支持某能力
     * 各厂商 API 能力差异大，调用方应先判断再调用
     * @param string $ability delete|copy|move|rename|mkdir|upload|download|listDir|read|thumbnail|previewUrl
     * @return bool
     */
    public function can($ability);
}
