<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdminPulse\Services\Telemetry;

/**
 * The exact p95 response time over every sampled request of the window.
 */
class ResponseTimeP95Widget extends PulseStatWidget
{
    public static function slug(): string
    {
        return 'admin.pulse.p95';
    }

    protected function tile(): array
    {
        $p95 = app(Telemetry::class)->requestSummary()['p95'];

        return [
            'label' => __('p95 времени ответа'),
            'value' => $p95 ?? '—',
            'suffix' => $p95 === null ? '' : ' '.__('мс'),
        ];
    }
}
