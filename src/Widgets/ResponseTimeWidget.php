<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdmin\Widget\ChartWidget;
use Dskripchenko\LaravelAdminPulse\Services\Telemetry;
use Dskripchenko\LaravelAdminPulse\Support\PulseAccess;

/**
 * p50 and p95 response time over the window, in milliseconds.
 *
 * A bucket without requests is a gap rather than a zero: "nothing was
 * measured" and "everything answered instantly" are different statements.
 */
class ResponseTimeWidget extends ChartWidget
{
    public function __construct()
    {
        $this->title(__('Время ответа, мс'))
            ->size(6)
            ->rowSpan(2)
            ->chartType('line')
            ->permission(PulseAccess::PERMISSION);
    }

    public static function slug(): string
    {
        return 'admin.pulse.response-time';
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
            // A null is drawn as a gap in the line.
            $chart->dataset('p50', $series['p50'])
                ->dataset('p95', $series['p95']);
        }

        return [
            ...$chart->data(),
            'description' => __('Перцентили по агрегатам admin:pulse:aggregate'),
        ];
    }
}
