<?php

namespace BasicHub\EsCore\Common\Notify;

use BasicHub\EsCore\Common\Notify\Message\MessageInterface;

/**
 * 通知工厂类
 * 提供统一的通知实例创建和消息发送入口
 */
class NotifyFactory
{
    // ================ 平台常量 ================
    const PLATFORM_FEISHU = 'Feishu';
    const PLATFORM_DINGTALK = 'Dingtalk';
    const PLATFORM_WECHAT = 'Wechat';
    const PLATFORM_WECOM = 'WeCom';

    // ================ 推送方式常量 ================
    const TYPE_APP = 'App';
    const TYPE_WEBHOOK = 'Webhook';
    const TYPE_TEMPLATE = 'Template';

    /**
     * 通过自建应用发送消息（一步到位）
     * @param string $platform 平台名称，使用 PLATFORM_* 常量
     * @param array $config 配置数组
     * @param MessageInterface $message 消息对象
     * @return mixed
     * @throws \Exception
     */
    public static function app(string $platform, array $config, MessageInterface $message)
    {
        $notifier = self::createApp($platform, $config);
        return $notifier->send($message);
    }

    /**
     * 通过 Webhook 发送消息（一步到位）
     * @param string $platform 平台名称，使用 PLATFORM_* 常量
     * @param array $config 配置数组
     * @param MessageInterface $message 消息对象
     * @return mixed
     * @throws \Exception
     */
    public static function webhook(string $platform, array $config, MessageInterface $message)
    {
        $notifier = self::createWebhook($platform, $config);
        return $notifier->send($message);
    }

    /**
     * 通过模板消息发送（一步到位）
     * @param string $platform 平台名称，使用 PLATFORM_* 常量
     * @param array $config 配置数组
     * @param MessageInterface $message 消息对象
     * @return mixed
     * @throws \Exception
     */
    public static function template(string $platform, array $config, MessageInterface $message)
    {
        $notifier = self::createTemplate($platform, $config);
        return $notifier->send($message);
    }

    /**
     * 创建自建应用实例
     * @param string $platform 平台名称，使用 PLATFORM_* 常量
     * @param array $config 配置数组
     * @return \BasicHub\EsCore\Common\Notify\App\Base
     * @throws \Exception
     */
    public static function createApp(string $platform, array $config)
    {
        $className = self::buildClassName($platform, self::TYPE_APP);
        return self::createInstance($className, $config);
    }

    /**
     * 创建 Webhook 实例
     * @param string $platform 平台名称，使用 PLATFORM_* 常量
     * @param array $config 配置数组
     * @return \BasicHub\EsCore\Common\Notify\Webhook\Base
     * @throws \Exception
     */
    public static function createWebhook(string $platform, array $config)
    {
        $className = self::buildClassName($platform, self::TYPE_WEBHOOK);
        return self::createInstance($className, $config);
    }

    /**
     * 创建模板消息实例
     * @param string $platform 平台名称，使用 PLATFORM_* 常量
     * @param array $config 配置数组
     * @return \BasicHub\EsCore\Common\Notify\Template\Base
     * @throws \Exception
     */
    public static function createTemplate(string $platform, array $config)
    {
        $className = self::buildClassName($platform, self::TYPE_TEMPLATE);
        return self::createInstance($className, $config);
    }

    /**
     * 构建类名
     * @param string $platform 平台名称
     * @param string $type 推送方式
     * @return string
     */
    private static function buildClassName(string $platform, string $type): string
    {
        return __NAMESPACE__ . "\\{$type}\\{$platform}";
    }

    /**
     * 创建实例
     * @param string $className 完整类名
     * @param array $config 配置数组
     * @return NotifyInterface
     * @throws \Exception
     */
    private static function createInstance(string $className, array $config)
    {
        if (!class_exists($className)) {
            throw new \Exception("通知类不存在: {$className}");
        }

        $instance = new $className($config);

        return $instance;
    }
}
