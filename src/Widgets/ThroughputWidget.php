<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdmin\Widget\ChartWidget;
use Dskripchenko\LaravelAdminPulse\Services\Telemetry;
use Dskripchenko\LaravelAdminPulse\Support\PulseAccess;

/**
 * Requests and 5xx responses per minute over the window — two lines on one
 * axis, so a spike of errors reads against the traffic it happened in.
 *
 * Drawn from the `requests` aggregates and scaled by the request sample rate.
 */
class ThroughputWidget extends ChartWidget
{
    public function __construct()
    {
        $this->title(__('Запросы в минуту'))
            ->size(6)
            ->rowSpan(2)
            ->chartType('line')
            ->permission(PulseAccess::PERMISSION);
    }

    public static function slug(): string
    {
        return 'admin.pulse.throughput';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        if (! PulseAccess::allowed()) {
            return ChartWidget::make()->chartType('line')->data();
        }

        $series = app(Telemetry::class)->requestSeries();

        $chart = ChartWidget::make()->chartType('line')->labels($series['labels']);
        if ($series['labels'] !== []) {
            $chart->dataset(__('Запросы'), $series['requests'])
                ->dataset(__('Ошибки 5xx'), $series['errors'], '#dc2626');
        }

        return [
            ...$chart->data(),
            'description' => __('Оценка по частоте сэмплирования, из агрегатов admin:pulse:aggregate'),
        ];
    }
}
