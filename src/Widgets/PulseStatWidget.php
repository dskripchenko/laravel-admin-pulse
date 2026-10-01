<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Dskripchenko\LaravelAdminPulse\Support\PulseAccess;

/**
 * One KPI tile over the telemetry window.
 *
 * One number per widget: the panel draws a stats widget as a single tile, so
 * four numbers are four widgets, each of which a user can move or hide on its
 * own.
 */
abstract class PulseStatWidget extends StatsOverviewWidget
{
    public function __construct()
    {
        $this->size(3)->permission(PulseAccess::PERMISSION);
    }

    /**
     * The tile: its label, its value and how it should look.
     *
     * @return array{label: string, value: int|float|string, suffix?: string, precision?: int, tone?: 'neutral'|'positive'|'negative'|'warning'|'info'}
     */
    abstract protected function tile(): array;

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        if (! PulseAccess::allowed()) {
            return StatsOverviewWidget::make()->data();
        }

        $tile = $this->tile();
        // Built on a fresh instance: stat() appends, and data() runs once per
        // payload — for the manifest and again for every refresh.
        $payload = StatsOverviewWidget::make()->stat($tile['label'], $tile['value'])->data();

        if (isset($tile['suffix'])) {
            $payload['stats'][0]['suffix'] = $tile['suffix'];
        }

        return [
            ...$payload,
            'tone' => $tile['tone'] ?? 'neutral',
            'precision' => $tile['precision'] ?? 0,
        ];
    }
}
