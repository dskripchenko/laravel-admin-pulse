# dskripchenko/laravel-admin-pulse

> 🌐 [English](../../README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · **中文**

面向管理面板的轻量级请求遥测：`pulse` 中间件将请求耗时采样写入数据库，管理面板中提供 **Telemetry samples** 列表，可按类型、键和时间段筛选。控制台命令将样本聚合为按路由的百分位数，并清理旧数据。自有实现，不依赖 `laravel/pulse`。

[`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin) 的姐妹包。

## 安装

```bash
composer require dskripchenko/laravel-admin-pulse
php artisan migrate
```

插件通过 Laravel package discovery 自动注册。发布配置：

```bash
php artisan vendor:publish --tag=admin-pulse-config
```

## 文档

- [快速开始](../../docs/en/getting-started.md) (en)
- [使用](../../docs/en/usage.md) (en)

## 许可证

[MIT](../../LICENSE) © Denis Skripchenko
