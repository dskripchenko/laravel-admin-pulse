# dskripchenko/laravel-admin-pulse

> 🌐 [English](../../README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · **中文**

面向管理面板的轻量级遥测：`pulse` 中间件将请求耗时采样写入数据库（数据库查询、队列任务和异常通过 `Sampler` 服务记录），管理面板中提供 **Telemetry** 仪表盘、顶部栏错误率指示器以及 **Telemetry samples** 列表。控制台命令将样本按时间窗口聚合为百分位数，并清理旧数据。自有实现，不依赖 `laravel/pulse`。

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

将聚合命令加入调度——仪表盘图表基于聚合数据绘制：

```php
Schedule::command('admin:pulse:aggregate')->everyFiveMinutes();
Schedule::command('admin:pulse:rotate')->daily();
```

## Telemetry 仪表盘

`/admin/dashboard/telemetry`，位于 “System” 菜单组，面向拥有 `admin.system.pulse.view` 权限的用户。由核心内置的组件类型构成，时间窗口为最近 24 小时：

- **指标卡片** — 估算请求数、5xx 错误率、p95 响应时间、异常数。
- **每分钟请求数** — 请求与 5xx 错误（折线图）。
- **响应时间** — p50 与 p95（折线图）。
- **最慢路由** — 路由、样本数、平均值、p95、最大值、5xx。
- **最慢查询** — SQL 指纹、样本数、平均值、最大值。
- **常见异常** — 异常、样本数、最后出现时间。
- **队列任务** — 每小时完成与失败数（堆叠柱状图）。

图表读取聚合数据，表格和指标卡片读取原始样本。当最近 15 分钟内 5xx 响应占比超过配置阈值时，顶部栏指示器显示为警告或错误。这些组件类也可以放到你自己的仪表盘上。

## 文档

- [快速开始](../../docs/en/getting-started.md) (en)
- [使用](../../docs/en/usage.md) (en)

## 要求

PHP 8.2+，Laravel 11–13，`dskripchenko/laravel-admin` ^1.33。

## 许可证

[MIT](../../LICENSE) © Denis Skripchenko
