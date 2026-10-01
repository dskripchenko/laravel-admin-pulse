<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Screens;

use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelAdminPulse\Services\Telemetry;
use Dskripchenko\LaravelAdminPulse\Support\PulseAccess;
use Dskripchenko\LaravelAdminPulse\Widgets\ErrorRateWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\ExceptionCountWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\JobsWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\RequestCountWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\ResponseTimeP95Widget;
use Dskripchenko\LaravelAdminPulse\Widgets\ResponseTimeWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\SlowQueriesWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\SlowRoutesWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\ThroughputWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\TopExceptionsWidget;

/**
 * The telemetry dashboard: KPI tiles, request and job charts, and the
 * slowest routes, slowest queries and most frequent exceptions.
 *
 * Every widget looks at the same window, `admin-pulse.dashboard.window_hours`
 * back from now; the dashboard's period selector does not change it, since
 * the samples are kept for hours, not for the days the selector offers.
 */
final class TelemetryDashboardScreen extends DashboardScreen
{
    public static function slug(): string
    {
        return 'telemetry';
    }

    public function name(): string
    {
        return __('Телеметрия');
    }

    public function description(): string
    {
        return __('Последние :hours ч', ['hours' => app(Telemetry::class)->windowHours()]);
    }

    public function permission(): string
    {
        return PulseAccess::PERMISSION;
    }

    public function widgets(): array
    {
        // The panel computes a dashboard's widgets for every signed-in user
        // while it builds the manifest. Without the permission there is
        // nothing to compute and nothing to send.
        if (! PulseAccess::allowed()) {
            return [];
        }

        return [
            RequestCountWidget::make(),
            ErrorRateWidget::make(),
            ResponseTimeP95Widget::make(),
            ExceptionCountWidget::make(),
            ThroughputWidget::make(),
            ResponseTimeWidget::make(),
            SlowRoutesWidget::make(),
            SlowQueriesWidget::make(),
            TopExceptionsWidget::make(),
            JobsWidget::make(),
        ];
    }
}
