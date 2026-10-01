<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdminPulse\Services\Telemetry;

/**
 * The query fingerprints with the highest average duration in the window.
 */
class SlowQueriesWidget extends PulseTableWidget
{
    public function __construct()
    {
        parent::__construct();
        $this->title(__('Медленные запросы к БД'));
    }

    public static function slug(): string
    {
        return 'admin.pulse.slow-queries';
    }

    protected function tableColumns(): array
    {
        return [
            TableColumn::make('query')->label(__('Запрос')),
            TableColumn::make('count')->label(__('Сэмплов'))->align('right'),
            TableColumn::make('avg_ms')->label(__('Среднее, мс'))->align('right'),
            TableColumn::make('max_ms')->label(__('Макс., мс'))->align('right'),
        ];
    }

    protected function rows(): array
    {
        return app(Telemetry::class)->slowQueries();
    }
}
