<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdminPulse\Services\Telemetry;

/**
 * The routes with the highest average response time in the window, with the
 * sample count, p95, maximum and the number of 5xx responses.
 */
class SlowRoutesWidget extends PulseTableWidget
{
    public function __construct()
    {
        parent::__construct();
        $this->title(__('Медленные маршруты'));
    }

    public static function slug(): string
    {
        return 'admin.pulse.slow-routes';
    }

    protected function tableColumns(): array
    {
        return [
            TableColumn::make('route')->label(__('Маршрут')),
            TableColumn::make('count')->label(__('Сэмплов'))->align('right'),
            TableColumn::make('avg_ms')->label(__('Среднее, мс'))->align('right'),
            TableColumn::make('p95_ms')->label(__('p95, мс'))->align('right'),
            TableColumn::make('max_ms')->label(__('Макс., мс'))->align('right'),
            TableColumn::make('errors')->label(__('Ошибки 5xx'))->align('right'),
        ];
    }

    protected function rows(): array
    {
        return app(Telemetry::class)->slowRoutes();
    }
}
