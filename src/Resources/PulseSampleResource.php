<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Resources;

use Dskripchenko\LaravelAdmin\Filter\DateRangeFilter;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Filter\OptionsFilter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Illuminate\Database\Eloquent\Builder;

/**
 * A view-only resource over admin_pulse_samples.
 *
 * Permissions: admin.system.pulse.view.
 */
final class PulseSampleResource extends Resource
{
    public static string $model = PulseSample::class;

    public static string $icon = 'activity';

    public static ?string $group = 'Системные';

    public static function slug(): string
    {
        return 'system-pulse-samples';
    }

    public static function permission(): string
    {
        return 'admin.system.pulse';
    }

    public static function label(): string
    {
        return __('Сэмплы телеметрии');
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort()->width('60px'),
            TableColumn::make('kind')->sort()->asBadge([
                'request' => 'info',
                'query' => 'default',
                'job' => 'success',
                'exception' => 'danger',
                'cache' => 'warning',
            ]),
            TableColumn::make('key')->search()->copyable(),
            TableColumn::make('label')->search(),
            TableColumn::make('duration_ms')->sort()->align('right'),
            TableColumn::make('status_code')->sort()->align('right'),
            TableColumn::make('sampled_at')->sort()->asDateTime(),
        ];
    }

    public function filters(): array
    {
        return [
            OptionsFilter::for('kind')->label(__('Тип'))->options([
                'request' => 'Request',
                'query' => 'Query',
                'job' => 'Job',
                'exception' => 'Exception',
                'cache' => 'Cache',
            ]),
            InputFilter::for('key')->label(__('Ключ (маршрут / отпечаток)')),
            DateRangeFilter::for('sampled_at')->label(__('Период')),
        ];
    }

    public function indexQuery(): Builder
    {
        return $this->modelQuery()->orderByDesc('sampled_at');
    }
}
