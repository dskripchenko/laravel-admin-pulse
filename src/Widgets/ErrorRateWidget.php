<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdminPulse\Services\Telemetry;

/**
 * The share of 5xx responses among the sampled requests of the window, in
 * percent; coloured by the indicator's thresholds.
 */
class ErrorRateWidget extends PulseStatWidget
{
    public static function slug(): string
    {
        return 'admin.pulse.error-rate';
    }

    protected function tile(): array
    {
        $rate = app(Telemetry::class)->requestSummary()['error_rate'];

        if ($rate === null) {
            return ['label' => __('Доля ошибок 5xx'), 'value' => '—'];
        }

        $tone = match (true) {
            $rate >= (float) config('admin-pulse.indicator.error_error_rate', 0.20) => 'negative',
            $rate >= (float) config('admin-pulse.indicator.warning_error_rate', 0.05) => 'warning',
            default => 'positive',
        };

        return [
            'label' => __('Доля ошибок 5xx'),
            'value' => round($rate * 100, 2),
            'suffix' => '%',
            'precision' => 2,
            'tone' => $tone,
        ];
    }
}
