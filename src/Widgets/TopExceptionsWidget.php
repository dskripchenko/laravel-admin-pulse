<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdminPulse\Services\Telemetry;

/**
 * The most frequent exceptions in the window, grouped by fingerprint, with
 * when each was last seen.
 */
class TopExceptionsWidget extends PulseTableWidget
{
    public function __construct()
    {
        parent::__construct();
        $this->title(__('Частые исключения'));
    }

    public static function slug(): string
    {
        return 'admin.pulse.top-exceptions';
    }

    protected function tableColumns(): array
    {
        return [
            TableColumn::make('exception')->label(__('Исключение')),
            TableColumn::make('count')->label(__('Сэмплов'))->align('right')->width('110px'),
            TableColumn::make('last_seen')->label(__('Последний раз'))->asDateTime()->width('180px'),
        ];
    }

    protected function rows(): array
    {
        return app(Telemetry::class)->topExceptions();
    }
}
