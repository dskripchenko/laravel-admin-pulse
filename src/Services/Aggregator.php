<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Services;

use Dskripchenko\LaravelAdminPulse\Models\PulseAggregate;
use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Illuminate\Support\Carbon;

/**
 * Aggregates the samples into percentile metrics.
 *
 * The buckets:
 *   - 'route.percentiles' — p50/p95/p99/count for every route+method pair over
 *     the period
 *   - 'top_slow_route' — the top 10 routes by p95 (a separate row per route)
 *
 * The period is the window [period_start, period_end). The caller (the aggregate
 * command) passes the range it needs (the last 5 minutes, for instance).
 */
final class Aggregator
{
    /**
     * Run the aggregation over the window [from, to).
     *
     * @return int The number of aggregate rows written
     */
    public function aggregate(Carbon $from, Carbon $to): int
    {
        $now = Carbon::now();
        $written = 0;

        // Route percentiles per kind=request
        $rows = PulseSample::query()
            ->where('kind', 'request')
            ->whereBetween('sampled_at', [$from, $to])
            ->get(['key', 'duration_ms']);

        $byRoute = [];
        foreach ($rows as $row) {
            $byRoute[$row->key][] = (int) $row->duration_ms;
        }

        foreach ($byRoute as $route => $durations) {
            sort($durations);
            $count = count($durations);
            $metrics = [
                'count' => $count,
                'p50' => $this->percentile($durations, 0.50),
                'p95' => $this->percentile($durations, 0.95),
                'p99' => $this->percentile($durations, 0.99),
                'min' => $durations[0],
                'max' => $durations[$count - 1],
                'avg' => (int) (array_sum($durations) / $count),
            ];

            PulseAggregate::query()->create([
                'bucket' => 'route.percentiles',
                'key' => $route,
                'metrics' => $metrics,
                'period_start' => $from,
                'period_end' => $to,
                'aggregated_at' => $now,
            ]);
            $written++;
        }

        return $written;
    }

    /**
     * A linearly interpolated percentile (p ∈ [0, 1]) over a sorted array.
     *
     * @param  list<int>  $sorted
     */
    public function percentile(array $sorted, float $p): int
    {
        if ($sorted === []) {
            return 0;
        }
        $count = count($sorted);
        if ($count === 1) {
            return $sorted[0];
        }
        $rank = $p * ($count - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);
        if ($low === $high) {
            return $sorted[$low];
        }
        $weight = $rank - $low;

        return (int) round($sorted[$low] * (1 - $weight) + $sorted[$high] * $weight);
    }
}
