<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Services;

use Dskripchenko\LaravelAdminPulse\Models\PulseAggregate;
use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the samples into percentile metrics.
 *
 * The buckets, one set per window:
 *   - 'route.percentiles' — count/errors/p50/p95/p99/min/max/avg for every
 *     route+method pair (key = the route)
 *   - 'requests' — the same metrics over every request of the window
 *     (key = '*'); the telemetry dashboard draws its time series from it
 *   - 'jobs' — the number of job samples and of failed ones (key = '*')
 *
 * A request is an error when its status code is 5xx; a job sample is a
 * failure when it was recorded with a 5xx status code (see the usage docs).
 *
 * The period is the window [period_start, period_end). The caller (the aggregate
 * command) passes the range it needs (the last 5 minutes, for instance).
 * Aggregating the same window again replaces its rows instead of adding a
 * second copy, so a retried scheduler run does not double the charts.
 */
final class Aggregator
{
    /** The buckets this class writes. */
    public const BUCKETS = ['route.percentiles', 'requests', 'jobs'];

    /**
     * Run the aggregation over the window [from, to).
     *
     * @return int The number of aggregate rows written
     */
    public function aggregate(Carbon $from, Carbon $to): int
    {
        $now = Carbon::now();
        $rows = [];

        $requests = PulseSample::query()
            ->where('kind', 'request')
            ->where('sampled_at', '>=', $from)
            ->where('sampled_at', '<', $to)
            ->get(['key', 'duration_ms', 'status_code']);

        $byRoute = [];
        $errorsByRoute = [];
        $all = [];
        $allErrors = 0;
        foreach ($requests as $row) {
            $duration = (int) $row->duration_ms;
            $isError = self::isError($row->status_code);
            $byRoute[$row->key][] = $duration;
            $errorsByRoute[$row->key] = ($errorsByRoute[$row->key] ?? 0) + ($isError ? 1 : 0);
            $all[] = $duration;
            $allErrors += $isError ? 1 : 0;
        }

        foreach ($byRoute as $route => $durations) {
            $rows[] = ['route.percentiles', (string) $route, $this->metrics($durations, $errorsByRoute[$route] ?? 0)];
        }

        if ($all !== []) {
            $rows[] = ['requests', '*', $this->metrics($all, $allErrors)];
        }

        $jobs = PulseSample::query()
            ->where('kind', 'job')
            ->where('sampled_at', '>=', $from)
            ->where('sampled_at', '<', $to)
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) AS failed')
            ->toBase()
            ->first();

        $jobTotal = (int) ($jobs->total ?? 0);
        if ($jobTotal > 0) {
            $rows[] = ['jobs', '*', ['count' => $jobTotal, 'failed' => (int) ($jobs->failed ?? 0)]];
        }

        DB::transaction(function () use ($rows, $from, $to, $now): void {
            PulseAggregate::query()
                ->whereIn('bucket', self::BUCKETS)
                ->where('period_start', $from)
                ->where('period_end', $to)
                ->delete();

            foreach ($rows as [$bucket, $key, $metrics]) {
                PulseAggregate::query()->create([
                    'bucket' => $bucket,
                    'key' => $key,
                    'metrics' => $metrics,
                    'period_start' => $from,
                    'period_end' => $to,
                    'aggregated_at' => $now,
                ]);
            }
        });

        return count($rows);
    }

    /**
     * @param  list<int>  $durations
     * @return array<string, int>
     */
    private function metrics(array $durations, int $errors): array
    {
        sort($durations);
        $count = count($durations);

        return [
            'count' => $count,
            'errors' => $errors,
            'p50' => $this->percentile($durations, 0.50),
            'p95' => $this->percentile($durations, 0.95),
            'p99' => $this->percentile($durations, 0.99),
            'min' => $durations[0],
            'max' => $durations[$count - 1],
            'avg' => (int) (array_sum($durations) / $count),
        ];
    }

    private static function isError(mixed $statusCode): bool
    {
        return $statusCode !== null && (int) $statusCode >= 500;
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
