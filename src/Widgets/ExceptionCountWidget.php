<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdminPulse\Services\Telemetry;

/**
 * The number of exception samples in the window.
 */
class ExceptionCountWidget extends PulseStatWidget
{
    public static function slug(): string
    {
        return 'admin.pulse.exception-count';
    }

    protected function tile(): array
    {
        $count = app(Telemetry::class)->exceptionCount();

        return [
            'label' => __('Исключений'),
            'value' => $count,
            'tone' => $count > 0 ? 'warning' : 'neutral',
        ];
    }
}
