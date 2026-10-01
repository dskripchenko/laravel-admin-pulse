<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdmin\Widget\ChartWidget;
use Dskripchenko\LaravelAdminPulse\Services\Telemetry;
use Dskripchenko\LaravelAdminPulse\Support\PulseAccess;

/**
 * Finished and failed jobs per bucket, stacked — the height is the
 * throughput, the red part the failures.
 *
 * Drawn from the `jobs` aggregates. A job sample counts as failed when it was
 * recorded with a 5xx status code.
 */
class JobsWidget extends ChartWidget
{
    public function __construct()
    {
        $this->title(__('Задания'))
            ->size(12)
            ->rowSpan(2)
            ->chartType('bar')
            ->stacked()
            ->permission(PulseAccess::PERMISSION);
    }

    public static function slug(): string
    {
        return 'admin.pulse.jobs';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        if (! PulseAccess::allowed()) {
            return ChartWidget::make()->chartType('bar')->stacked()->data();
        }

        $series = app(Telemetry::class)->jobSeries();

        $chart = ChartWidget::make()->chartType('bar')->stacked()->labels($series['labels']);
        if ($series['labels'] !== []) {
            $chart->dataset(__('Выполнено'), $series['processed'])
                ->dataset(__('Упало'), $series['failed'], '#dc2626');
        }

        return [
            ...$chart->data(),
            'description' => __('Из агрегатов admin:pulse:aggregate'),
        ];
    }
}
