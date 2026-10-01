<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Status;

use Dskripchenko\LaravelAdmin\Status\StatusIndicator;
use Dskripchenko\LaravelAdminPulse\Screens\TelemetryDashboardScreen;
use Dskripchenko\LaravelAdminPulse\Services\Telemetry;
use Dskripchenko\LaravelAdminPulse\Support\PulseAccess;
use Illuminate\Support\Carbon;

/**
 * The share of 5xx responses, in the top bar.
 *
 * The panel hides an indicator whose status is `ok`, so this one speaks only
 * when the error rate of the last few minutes crosses a threshold — and only
 * to the users allowed to see telemetry. Too few requests to judge is also
 * `ok`: two failures out of three requests at night is not an incident.
 */
final class PulseStatusIndicator implements StatusIndicator
{
    public function __construct(private readonly Telemetry $telemetry) {}

    public function key(): string
    {
        return 'admin.pulse';
    }

    /**
     * @return array{status: 'ok'|'warning'|'error'|'unknown', label: string, detail?: string, url?: string}
     */
    public function state(): array
    {
        if (! PulseAccess::allowed()) {
            return ['status' => 'ok', 'label' => ''];
        }

        $minutes = max(1, (int) config('admin-pulse.indicator.window_minutes', 15));
        $totals = $this->telemetry->errorsSince(Carbon::now()->subMinutes($minutes));

        if ($totals['count'] < max(1, (int) config('admin-pulse.indicator.min_requests', 20))) {
            return ['status' => 'ok', 'label' => __('Ошибки 5xx в норме')];
        }

        $rate = $totals['errors'] / $totals['count'];
        $status = match (true) {
            $rate >= (float) config('admin-pulse.indicator.error_error_rate', 0.20) => 'error',
            $rate >= (float) config('admin-pulse.indicator.warning_error_rate', 0.05) => 'warning',
            default => 'ok',
        };

        return [
            'status' => $status,
            'label' => $status === 'ok'
                ? __('Ошибки 5xx в норме')
                : __('Ошибки 5xx: :rate%', ['rate' => round($rate * 100, 1)]),
            'detail' => __(':errors из :count запросов за :minutes мин', [
                'errors' => $totals['errors'],
                'count' => $totals['count'],
                'minutes' => $minutes,
            ]),
            'url' => '/dashboard/'.TelemetryDashboardScreen::slug(),
        ];
    }
}
