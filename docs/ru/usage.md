---
title: Использование
locale: ru
status: stable
translated_from: ../en/usage.md
---

# Использование

## Планировщик

```php
// routes/console.php (или ваш планировщик)
Schedule::command('admin:pulse:aggregate')->everyFiveMinutes();
Schedule::command('admin:pulse:rotate')->daily();
```

`admin:pulse:aggregate --minutes=5` сворачивает последнее полное окно сэмплов в `admin_pulse_aggregates`:

| Bucket | Ключ | Метрики |
|---|---|---|
| `route.percentiles` | маршрут (`GET api/orders`) | `count`, `errors`, `p50`, `p95`, `p99`, `min`, `max`, `avg` |
| `requests` | `*` | те же метрики по всем запросам окна |
| `jobs` | `*` | `count`, `failed` |

Запрос считается ошибкой, если код ответа 5xx. Повторная агрегация того же окна заменяет его строки, поэтому повторный запуск не удваивает цифры.

`--hours=N` догоняет пропущенное: сворачивает каждое полное окно последних N часов, а не только последнее. Запускайте его после простоя планировщика или после импорта сэмплов:

```bash
php artisan admin:pulse:aggregate --hours=24
```

`admin:pulse:rotate` удаляет сэмплы и агрегаты старше заданных сроков хранения.

## Дашборд «Телеметрия»

Плагин регистрирует `DashboardScreen` со слагом `telemetry` (`/admin/dashboard/telemetry`) и пункт меню в группе «Системные». Все виджеты смотрят на одно окно — последние `dashboard.window_hours` часов (по умолчанию 24). Переключатель периода на дашборде его не меняет: сэмплы хранятся часы, а не дни, которые предлагает переключатель.

| Виджет | Тип | Источник |
|---|---|---|
| Запросов за 24 ч | плитка | сэмплы: их число, делённое на частоту сэмплирования запросов |
| Доля ошибок 5xx | плитка | сэмплы; цвет — по порогам индикатора |
| p95 времени ответа | плитка | сэмплы; точный перцентиль по окну |
| Исключений | плитка | сэмплы |
| Запросы в минуту | линейный график: запросы, ошибки 5xx | агрегаты `requests` с поправкой на частоту сэмплирования |
| Время ответа, мс | линейный график: p50, p95 | агрегаты `requests` |
| Медленные маршруты | таблица: маршрут, сэмплы, среднее, p95, максимум, 5xx | сэмплы |
| Медленные запросы к БД | таблица: запрос, сэмплы, среднее, максимум | сэмплы |
| Частые исключения | таблица: исключение, сэмплы, последний раз | сэмплы |
| Задания | столбцы с накоплением: выполнено, упало | агрегаты `jobs` |

Как считаются цифры:

- Графики делят окно на `dashboard.buckets` равных интервалов (по умолчанию 24 часовых). p50/p95 интервала — средние по пятиминутным окнам внутри него, взвешенные по числу запросов: точно внутри окна, приближённо между окнами. Интервал без запросов — разрыв в линиях времени ответа, а не ноль.
- Каждая таблица — один запрос с группировкой по окну. p95 маршрута точный: база читает значение на ранге перцентиля (та же линейная интерполяция, что и в агрегаторе), по одному небольшому запросу на маршрут в таблице.
- Результаты кешируются на `dashboard.cache_seconds` секунд (по умолчанию 30).

Классы виджетов лежат в `Dskripchenko\LaravelAdminPulse\Widgets`, их можно ставить на свой дашборд:

```php
use Dskripchenko\LaravelAdminPulse\Widgets\ErrorRateWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\ResponseTimeWidget;

public function widgets(): array
{
    return [
        ErrorRateWidget::make(),
        ResponseTimeWidget::make()->size(12),
    ];
}
```

Каждый виджет сам проверяет `admin.system.pulse.view` и пользователю без этого права отдаёт пустые данные.

## Индикатор в верхней панели

`PulseStatusIndicator` показывает долю ответов 5xx среди сэмплированных запросов за последние `indicator.window_minutes` минут. Если сэмплов меньше `indicator.min_requests` или доля ниже `indicator.warning_error_rate`, статус `ok` и панель ничего не показывает; начиная с `warning_error_rate` — предупреждение, с `error_error_rate` — ошибка. Клик открывает дашборд «Телеметрия». Пользователи без `admin.system.pulse.view` индикатор не видят.

## Конфигурация

```php
// config/admin-pulse.php
'enabled' => env('ADMIN_PULSE_ENABLED', true),

'sample_rate' => [
    'request' => 0.1,   // 10%
    'query' => 0.1,
    'job' => 1.0,
    'exception' => 1.0,
    'cache' => 0.05,
],

'retention' => [
    'samples_hours' => 24,
    'aggregates_days' => 7,
],

'dashboard' => [
    'enabled' => true,       // register the dashboard and its menu entry
    'window_hours' => 24,
    'buckets' => 24,
    'top' => 10,             // rows per table
    'cache_seconds' => 30,
],

'indicator' => [
    'enabled' => true,
    'window_minutes' => 15,
    'min_requests' => 20,
    'warning_error_rate' => 0.05,
    'error_error_rate' => 0.20,
],
```

Таблицы и плитки читают сырые сэмплы, поэтому при `window_hours` больше `retention.samples_hours` видно только то, что ещё хранится.

## Сэмплы запросов

Middleware `pulse` ключует запрос методом и маршрутом (`GET api/products/{product}`). Значения параметров из `key_parameters` подставляются в ключ, поэтому общий маршрут laravel-api `api/{version}/{controller}/{action}` даёт по ключу на эндпоинт (`POST api/admin/orders/search`), а не один на весь API; остальные параметры остаются шаблоном. Сэмпл пишется после отправки ответа из колбэка `terminating` приложения, поэтому работает и для группы, запущенной внутри другого конвейера (admin API).

```php
'key_parameters' => ['version', 'controller', 'action'],
```

## Запись других типов

Middleware пишет только сэмплы `request`. Запросы к БД, задания, исключения и кеш хост пишет сам через сервис `Sampler`. Дашборд ожидает такие ключи:

| Тип | `key` | `label` | `status_code` |
|---|---|---|---|
| `query` | `Sampler::fingerprintSql($sql)` | — | — |
| `job` | класс задания | — | `500` для упавшего задания, меньше — для выполненного |
| `exception` | `Sampler::fingerprintException($e)` | `ClassName: message` (выводится в таблице) | — |

```php
use Dskripchenko\LaravelAdminPulse\Services\Sampler;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

// boot() сервис-провайдера
$sampler = app(Sampler::class);

DB::listen(function (QueryExecuted $query) use ($sampler): void {
    // Запись сэмпла — тоже запрос: таблицы пакета пропускаем, иначе каждый
    // сэмпл пишет следующий (при rate 1.0 — без конца).
    if (str_contains($query->sql, 'admin_pulse_')) {
        return;
    }
    if ($sampler->shouldSample('query')) {
        $sampler->record('query', mb_substr($sampler->fingerprintSql($query->sql), 0, 255), (int) $query->time);
    }
});

Event::listen(JobProcessed::class, function (JobProcessed $event) use ($sampler): void {
    if ($sampler->shouldSample('job')) {
        $sampler->record('job', $event->job->resolveName(), 0, statusCode: 200);
    }
});

Event::listen(JobFailed::class, function (JobFailed $event) use ($sampler): void {
    if ($sampler->shouldSample('job')) {
        $sampler->record('job', $event->job->resolveName(), 0, statusCode: 500);
    }
});

// bootstrap/app.php → withExceptions()
$exceptions->report(function (Throwable $e): void {
    $sampler = app(Sampler::class);
    if ($sampler->shouldSample('exception')) {
        $sampler->record(
            'exception',
            $sampler->fingerprintException($e),
            0,
            label: mb_substr(get_class($e).': '.$e->getMessage(), 0, 255),
        );
    }
});
```

`record()` принимает необязательный `sampledAt` (любой `DateTimeInterface`, по умолчанию — сейчас) для сэмплов, записанных задним числом: импорт, дозаполнение, сидер. После них сверните окна командой `admin:pulse:aggregate --hours=N`.
