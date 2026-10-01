<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdminPulse\Services\Telemetry;

/**
 * The estimated number of requests in the window: the sampled ones divided
 * by the request sample rate.
 */
class RequestCountWidget extends PulseStatWidget
{
    public static function slug(): string
    {
        return 'admin.pulse.request-count';
    }

    protected function tile(): array
    {
        $telemetry = app(Telemetry::class);

        return [
            'label' => __('Запросов за :hours ч', ['hours' => $telemetry->windowHours()]),
            'value' => $telemetry->requestSummary()['estimated'],
        ];
    }
}
