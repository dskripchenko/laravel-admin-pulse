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
            // Every column carries a label: one made from the column name
            // stays English in every panel language.
            TableColumn::make('id')->label(__('ID'))->sort()->width('60px'),
            TableColumn::make('kind')->label(__('Тип'))->sort()->asBadge([
                'request' => 'info',
                'query' => 'default',
                'job' => 'success',
                'exception' => 'danger',
                'cache' => 'warning',
            ], self::kinds()),
            TableColumn::make('key')->label(__('Ключ'))->search()->copyable(),
            TableColumn::make('label')->label(__('Подпись'))->search(),
            TableColumn::make('duration_ms')->label(__('Длительность, мс'))->sort()->align('right'),
            TableColumn::make('status_code')->label(__('Код ответа'))->sort()->align('right'),
            TableColumn::make('sampled_at')->label(__('Время'))->sort()->asDateTime(),
        ];
    }

    public function filters(): array
    {
        return [
            OptionsFilter::for('kind')->label(__('Тип'))->options(self::kinds()),
            InputFilter::for('key')->label(__('Ключ (маршрут / отпечаток)')),
            DateRangeFilter::for('sampled_at')->label(__('Период')),
        ];
    }

    /**
     * The sample kinds and their captions — source strings, translated per
     * request by core.
     *
     * @return array<string, string>
     */
    private static function kinds(): array
    {
        return [
            'request' => 'HTTP-запрос',
            'query' => 'SQL-запрос',
            'job' => 'Задача',
            'exception' => 'Исключение',
            'cache' => 'Кэш',
        ];
    }

    public function indexQuery(): Builder
    {
        return $this->modelQuery()->orderByDesc('sampled_at');
    }
}
