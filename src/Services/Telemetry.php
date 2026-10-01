<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Services;

use Closure;
use Dskripchenko\LaravelAdminPulse\Models\PulseAggregate;
use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The read side of the telemetry: what the dashboard widgets and the top-bar
 * indicator show.
 *
 * Two sources, each used where it is exact:
 *   - the time series (throughput, p50/p95, jobs) come from the aggregates the
 *     `admin:pulse:aggregate` command writes — a 24-hour chart reads a few
 *     hundred rows instead of every sample of the day;
 *   - the tables and the KPI tiles come from the raw samples, through grouped
 *     queries over the bounded window: one GROUP BY per table, plus at most
 *     `top` small percentile lookups for the routes it returns.
 *
 * Every query is bounded by the window (`dashboard.window_hours`), and every
 * result is cached for `dashboard.cache_seconds`: the payloads are computed
 * whenever the panel manifest is built, not only when the dashboard is open.
 */
final class Telemetry
{
    /** A status code from this one up is an error (requests) or a failure (jobs). */
    public const ERROR_STATUS = 500;

    public function windowHours(): int
    {
        return max(1, (int) config('admin-pulse.dashboard.window_hours', 24));
    }

    public function top(): int
    {
        return max(1, (int) config('admin-pulse.dashboard.top', 10));
    }

    /** The start of the window the tables and the tiles look at. */
    public function since(): Carbon
    {
        return Carbon::now()->subHours($this->windowHours());
    }

    /**
     * Requests over time, from the `requests` aggregates.
     *
     * The throughput is per minute and estimated: the sampled count divided by
     * the configured request sample rate. p50/p95 of a bucket are the
     * count-weighted means of the aggregated windows inside it (exact within a
     * window, an approximation across windows); a bucket without requests has
     * null latencies, which the chart draws as a gap.
     *
     * @return array{labels: list<string>, requests: list<float>, errors: list<float>, p50: list<int|null>, p95: list<int|null>}
     */
    public function requestSeries(): array
    {
        return $this->remember('requests', function (): array {
            $grid = $this->grid();
            $rows = $this->aggregates('requests', $grid);

            if ($rows === []) {
                return ['labels' => [], 'requests' => [], 'errors' => [], 'p50' => [], 'p95' => []];
            }

            $n = count($grid['starts']);
            $count = array_fill(0, $n, 0);
            $errors = array_fill(0, $n, 0);
            $p50 = array_fill(0, $n, 0);
            $p95 = array_fill(0, $n, 0);
            $coveredUntil = 0;

            foreach ($rows as $row) {
                $i = $row['index'];
                $c = (int) ($row['metrics']['count'] ?? 0);
                $count[$i] += $c;
                $errors[$i] += (int) ($row['metrics']['errors'] ?? 0);
                $p50[$i] += $c * (int) ($row['metrics']['p50'] ?? 0);
                $p95[$i] += $c * (int) ($row['metrics']['p95'] ?? 0);
                $coveredUntil = max($coveredUntil, $row['end']);
            }

            $rate = $this->sampleRate('request');
            $out = ['labels' => $grid['labels'], 'requests' => [], 'errors' => [], 'p50' => [], 'p95' => []];
            foreach ($grid['starts'] as $i => $start) {
                $minutes = $this->bucketMinutes($start, $grid['size'], $coveredUntil);
                $out['requests'][] = round($count[$i] / $rate / $minutes, 2);
                $out['errors'][] = round($errors[$i] / $rate / $minutes, 2);
                $out['p50'][] = $count[$i] > 0 ? (int) round($p50[$i] / $count[$i]) : null;
                $out['p95'][] = $count[$i] > 0 ? (int) round($p95[$i] / $count[$i]) : null;
            }

            return $out;
        });
    }

    /**
     * Jobs over time, from the `jobs` aggregates: how many finished and how
     * many failed in every bucket, estimated through the job sample rate.
     *
     * @return array{labels: list<string>, processed: list<int>, failed: list<int>}
     */
    public function jobSeries(): array
    {
        return $this->remember('jobs', function (): array {
            $grid = $this->grid();
            $rows = $this->aggregates('jobs', $grid);

            if ($rows === []) {
                return ['labels' => [], 'processed' => [], 'failed' => []];
            }

            $n = count($grid['starts']);
            $total = array_fill(0, $n, 0);
            $failed = array_fill(0, $n, 0);
            foreach ($rows as $row) {
                $total[$row['index']] += (int) ($row['metrics']['count'] ?? 0);
                $failed[$row['index']] += (int) ($row['metrics']['failed'] ?? 0);
            }

            $rate = $this->sampleRate('job');
            $out = ['labels' => $grid['labels'], 'processed' => [], 'failed' => []];
            for ($i = 0; $i < $n; $i++) {
                $out['processed'][] = (int) round(($total[$i] - $failed[$i]) / $rate);
                $out['failed'][] = (int) round($failed[$i] / $rate);
            }

            return $out;
        });
    }

    /**
     * The request KPIs over the window, from the samples: the sampled count,
     * the estimated real count, the errors, the error rate (0..1, null without
     * requests) and the exact p95.
     *
     * @return array{samples: int, estimated: int, errors: int, error_rate: float|null, p95: int|null}
     */
    public function requestSummary(): array
    {
        return $this->remember('summary', function (): array {
            $since = $this->since();
            $totals = $this->samples('request', $since)
                ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN status_code >= ? THEN 1 ELSE 0 END) AS errors', [self::ERROR_STATUS])
                ->first();

            $samples = (int) ($totals->total ?? 0);
            $errors = (int) ($totals->errors ?? 0);

            return [
                'samples' => $samples,
                'estimated' => (int) round($samples / $this->sampleRate('request')),
                'errors' => $errors,
                'error_rate' => $samples > 0 ? $errors / $samples : null,
                'p95' => $this->percentile($this->samples('request', $since), $samples, 0.95),
            ];
        });
    }

    /** The number of exception samples in the window. */
    public function exceptionCount(): int
    {
        return $this->remember('exception-count', fn (): int => $this->samples('exception', $this->since())->count());
    }

    /**
     * The slowest routes by average response time, with an exact p95 each.
     *
     * @return list<array{route: string, count: int, avg_ms: int, p95_ms: int, max_ms: int, errors: int}>
     */
    public function slowRoutes(): array
    {
        return $this->remember('routes', function (): array {
            $since = $this->since();
            $rows = $this->samples('request', $since)
                ->select('key')
                ->selectRaw('COUNT(*) AS hits, AVG(duration_ms) AS avg_ms, MAX(duration_ms) AS max_ms')
                ->selectRaw('SUM(CASE WHEN status_code >= ? THEN 1 ELSE 0 END) AS errors', [self::ERROR_STATUS])
                ->groupBy('key')
                ->orderByDesc('avg_ms')
                ->orderBy('key')
                ->limit($this->top())
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $hits = (int) $row->hits;
                $out[] = [
                    'route' => (string) $row->key,
                    'count' => $hits,
                    'avg_ms' => (int) round((float) $row->avg_ms),
                    // One small indexed lookup per returned route — `top` of
                    // them at most, never one per sample.
                    'p95_ms' => (int) $this->percentile(
                        $this->samples('request', $since)->where('key', $row->key),
                        $hits,
                        0.95,
                    ),
                    'max_ms' => (int) $row->max_ms,
                    'errors' => (int) $row->errors,
                ];
            }

            return $out;
        });
    }

    /**
     * The slowest query fingerprints by average duration.
     *
     * @return list<array{query: string, count: int, avg_ms: int, max_ms: int}>
     */
    public function slowQueries(): array
    {
        return $this->remember('queries', function (): array {
            return $this->samples('query', $this->since())
                ->select('key')
                ->selectRaw('COUNT(*) AS hits, AVG(duration_ms) AS avg_ms, MAX(duration_ms) AS max_ms')
                ->groupBy('key')
                ->orderByDesc('avg_ms')
                ->orderBy('key')
                ->limit($this->top())
                ->get()
                ->map(static fn (object $row): array => [
                    'query' => (string) $row->key,
                    'count' => (int) $row->hits,
                    'avg_ms' => (int) round((float) $row->avg_ms),
                    'max_ms' => (int) $row->max_ms,
                ])
                ->values()
                ->all();
        });
    }

    /**
     * The most frequent exceptions, grouped by their key (the fingerprint),
     * with the label they were recorded with and when they were last seen.
     *
     * @return list<array{exception: string, key: string, count: int, last_seen: string}>
     */
    public function topExceptions(): array
    {
        return $this->remember('exceptions', function (): array {
            return $this->samples('exception', $this->since())
                ->select('key')
                ->selectRaw('COUNT(*) AS hits, MAX(sampled_at) AS last_seen, MAX(label) AS label')
                ->groupBy('key')
                ->orderByDesc('hits')
                ->orderByDesc('last_seen')
                ->limit($this->top())
                ->get()
                ->map(static fn (object $row): array => [
                    'exception' => (string) (($row->label ?? '') !== '' ? $row->label : $row->key),
                    'key' => (string) $row->key,
                    'count' => (int) $row->hits,
                    'last_seen' => Carbon::parse((string) $row->last_seen)->toIso8601String(),
                ])
                ->values()
                ->all();
        });
    }

    /**
     * The sampled requests and the 5xx among them since a moment — the
     * indicator's question. Not cached: it is one indexed count.
     *
     * @return array{count: int, errors: int}
     */
    public function errorsSince(Carbon $since): array
    {
        $row = $this->samples('request', $since)
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN status_code >= ? THEN 1 ELSE 0 END) AS errors', [self::ERROR_STATUS])
            ->first();

        return ['count' => (int) ($row->total ?? 0), 'errors' => (int) ($row->errors ?? 0)];
    }

    /**
     * The linearly interpolated percentile of `duration_ms` over a query —
     * the same definition as Aggregator::percentile(), computed by the
     * database: it reads the two neighbouring values at the rank instead of
     * loading every duration.
     */
    public function percentile(Builder $query, int $count, float $p): ?int
    {
        if ($count <= 0) {
            return null;
        }

        $rank = $p * ($count - 1);
        $low = (int) floor($rank);

        /** @var list<int> $values */
        $values = $query
            ->orderBy('duration_ms')
            ->offset($low)
            ->limit(2)
            ->pluck('duration_ms')
            ->map(static fn (mixed $v): int => (int) $v)
            ->values()
            ->all();

        if ($values === []) {
            return null;
        }

        $weight = $rank - $low;
        if ($weight <= 0.0 || ! isset($values[1])) {
            return $values[0];
        }

        return (int) round($values[0] * (1 - $weight) + $values[1] * $weight);
    }

    /**
     * The chart buckets: `buckets` equal slices ending with the one that holds
     * now, aligned to the bucket length in the application's time zone.
     *
     * @return array{size: int, starts: list<int>, labels: list<string>, from: int, to: int}
     */
    private function grid(): array
    {
        $buckets = max(1, (int) config('admin-pulse.dashboard.buckets', 24));
        $size = max(60, intdiv($this->windowHours() * 3600, $buckets));

        $now = Carbon::now();
        $offset = $now->getOffset();
        $last = intdiv($now->getTimestamp() + $offset, $size) * $size - $offset;
        $from = $last - ($buckets - 1) * $size;

        $format = $this->windowHours() > 24 ? 'd.m H:i' : 'H:i';
        $starts = [];
        $labels = [];
        for ($i = 0; $i < $buckets; $i++) {
            $start = $from + $i * $size;
            $starts[] = $start;
            $labels[] = Carbon::createFromTimestamp($start, $now->getTimezone())->format($format);
        }

        return ['size' => $size, 'starts' => $starts, 'labels' => $labels, 'from' => $from, 'to' => $last + $size];
    }

    /**
     * The aggregate rows of one bucket inside the grid, each with the index of
     * the chart bucket it falls into.
     *
     * @param  array{size: int, starts: list<int>, labels: list<string>, from: int, to: int}  $grid
     * @return list<array{index: int, end: int, metrics: array<string, mixed>}>
     */
    private function aggregates(string $bucket, array $grid): array
    {
        $rows = PulseAggregate::query()
            ->where('bucket', $bucket)
            ->where('period_start', '>=', Carbon::createFromTimestamp($grid['from'], date_default_timezone_get()))
            ->where('period_start', '<', Carbon::createFromTimestamp($grid['to'], date_default_timezone_get()))
            ->get(['period_start', 'period_end', 'metrics']);

        $out = [];
        foreach ($rows as $row) {
            $index = intdiv($row->period_start->getTimestamp() - $grid['from'], $grid['size']);
            if ($index < 0 || $index >= count($grid['starts'])) {
                continue;
            }
            $out[] = [
                'index' => $index,
                'end' => $row->period_end->getTimestamp(),
                'metrics' => (array) $row->metrics,
            ];
        }

        return $out;
    }

    /**
     * The minutes a bucket covers: the whole bucket, except the newest one,
     * which is only covered up to the end of the last aggregated window.
     */
    private function bucketMinutes(int $start, int $size, int $coveredUntil): float
    {
        $covered = min($start + $size, max($coveredUntil, $start)) - $start;

        return ($covered > 0 && $coveredUntil < $start + $size ? $covered : $size) / 60;
    }

    private function samples(string $kind, Carbon $since): Builder
    {
        return PulseSample::query()
            ->toBase()
            ->where('kind', $kind)
            ->where('sampled_at', '>=', $since);
    }

    private function sampleRate(string $kind): float
    {
        $rate = (float) config("admin-pulse.sample_rate.$kind", 1.0);

        return $rate > 0.0 && $rate <= 1.0 ? $rate : 1.0;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    private function remember(string $key, Closure $compute): mixed
    {
        $seconds = (int) config('admin-pulse.dashboard.cache_seconds', 30);
        if ($seconds <= 0) {
            return $compute();
        }

        $signature = implode(':', [
            $key,
            $this->windowHours(),
            (int) config('admin-pulse.dashboard.buckets', 24),
            $this->top(),
            Carbon::now()->getTimezone()->getName(),
        ]);

        return Cache::remember('admin-pulse:telemetry:'.$signature, $seconds, $compute);
    }
}
