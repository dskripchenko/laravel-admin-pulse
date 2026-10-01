<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests\Feature;

use Dskripchenko\LaravelAdminPulse\Services\Aggregator;
use Dskripchenko\LaravelAdminPulse\Services\Telemetry;
use Dskripchenko\LaravelAdminPulse\Tests\TestCase;
use Illuminate\Support\Carbon;

final class TelemetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-01 12:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function telemetry(): Telemetry
    {
        return app(Telemetry::class);
    }

    public function test_everything_is_empty_without_data(): void
    {
        $t = $this->telemetry();

        $this->assertSame(['labels' => [], 'requests' => [], 'errors' => [], 'p50' => [], 'p95' => []], $t->requestSeries());
        $this->assertSame(['labels' => [], 'processed' => [], 'failed' => []], $t->jobSeries());
        $this->assertSame([], $t->slowRoutes());
        $this->assertSame([], $t->slowQueries());
        $this->assertSame([], $t->topExceptions());
        $this->assertSame(0, $t->exceptionCount());
        $this->assertSame(
            ['samples' => 0, 'estimated' => 0, 'errors' => 0, 'error_rate' => null, 'p95' => null],
            $t->requestSummary(),
        );
    }

    public function test_slow_routes_have_exact_percentiles_and_respect_the_window(): void
    {
        $durations = [];
        for ($i = 1; $i <= 20; $i++) {
            $ms = $i * 13 % 97 + $i;
            $durations[] = $ms;
            $this->sample('request', 'GET api/slow', $ms, '2026-10-01 11:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00', $i <= 2 ? 500 : 200);
        }
        $this->sample('request', 'GET api/fast', 5, '2026-10-01 12:00:00', 200);
        // Older than the 24-hour window: ignored.
        $this->sample('request', 'GET api/slow', 100000, '2026-09-30 12:29:00', 200);
        $this->sample('request', 'GET api/ancient', 100000, '2026-09-29 12:00:00', 200);

        sort($durations);
        $expectedP95 = (new Aggregator)->percentile($durations, 0.95);

        $routes = $this->telemetry()->slowRoutes();

        $this->assertCount(2, $routes);
        $this->assertSame('GET api/slow', $routes[0]['route']);
        $this->assertSame(20, $routes[0]['count']);
        $this->assertSame($expectedP95, $routes[0]['p95_ms']);
        $this->assertSame(max($durations), $routes[0]['max_ms']);
        $this->assertSame((int) round(array_sum($durations) / 20), $routes[0]['avg_ms']);
        $this->assertSame(2, $routes[0]['errors']);
        $this->assertSame(['route' => 'GET api/fast', 'count' => 1, 'avg_ms' => 5, 'p95_ms' => 5, 'max_ms' => 5, 'errors' => 0], $routes[1]);
    }

    public function test_top_limits_the_tables(): void
    {
        config()->set('admin-pulse.dashboard.top', 3);
        for ($i = 1; $i <= 5; $i++) {
            $this->sample('request', "GET r$i", $i * 10, '2026-10-01 12:00:00', 200);
        }

        $routes = $this->telemetry()->slowRoutes();

        $this->assertSame(['GET r5', 'GET r4', 'GET r3'], array_column($routes, 'route'));
    }

    public function test_percentile_matches_the_aggregator_definition(): void
    {
        $values = [7, 1, 9, 4, 4, 12, 30, 2];
        foreach ($values as $ms) {
            $this->sample('request', 'GET x', $ms, '2026-10-01 12:00:00', 200);
        }
        sort($values);

        foreach ([0.0, 0.5, 0.95, 0.99, 1.0] as $p) {
            $query = \Dskripchenko\LaravelAdminPulse\Models\PulseSample::query()->toBase()->where('kind', 'request');
            $this->assertSame((new Aggregator)->percentile($values, $p), $this->telemetry()->percentile($query, count($values), $p), "p=$p");
        }
    }

    public function test_request_summary(): void
    {
        config()->set('admin-pulse.sample_rate.request', 0.5);
        foreach ([10, 20, 30, 40] as $i => $ms) {
            $this->sample('request', 'GET a', $ms, '2026-10-01 12:00:0'.$i, $i === 0 ? 502 : 200);
        }

        $summary = $this->telemetry()->requestSummary();

        $this->assertSame(4, $summary['samples']);
        // Scaled by the 50% sample rate.
        $this->assertSame(8, $summary['estimated']);
        $this->assertSame(1, $summary['errors']);
        $this->assertSame(0.25, $summary['error_rate']);
        $this->assertSame(39, $summary['p95']);
    }

    public function test_slow_queries_and_top_exceptions(): void
    {
        $this->sample('query', 'select * from users where id = ?', 10, '2026-10-01 12:00:00');
        $this->sample('query', 'select * from users where id = ?', 30, '2026-10-01 12:01:00');
        $this->sample('query', 'select 1', 1, '2026-10-01 12:01:00');

        $this->sample('exception', 'abc', 0, '2026-10-01 11:00:00', label: 'RuntimeException: boom');
        $this->sample('exception', 'abc', 0, '2026-10-01 12:10:00', label: 'RuntimeException: boom');
        $this->sample('exception', 'def', 0, '2026-10-01 12:20:00');

        $queries = $this->telemetry()->slowQueries();
        $this->assertSame([
            ['query' => 'select * from users where id = ?', 'count' => 2, 'avg_ms' => 20, 'max_ms' => 30],
            ['query' => 'select 1', 'count' => 1, 'avg_ms' => 1, 'max_ms' => 1],
        ], $queries);

        $exceptions = $this->telemetry()->topExceptions();
        $this->assertCount(2, $exceptions);
        $this->assertSame('RuntimeException: boom', $exceptions[0]['exception']);
        $this->assertSame(2, $exceptions[0]['count']);
        $this->assertSame(Carbon::parse('2026-10-01 12:10:00')->toIso8601String(), $exceptions[0]['last_seen']);
        // No label recorded: the fingerprint stands in.
        $this->assertSame('def', $exceptions[1]['exception']);

        $this->assertSame(3, $this->telemetry()->exceptionCount());
    }

    public function test_request_series_is_bucketed_from_aggregates(): void
    {
        // Two aggregated windows in the 11:00 bucket, one in the current 12:00 bucket.
        foreach (range(1, 10) as $i) {
            $this->sample('request', 'GET a', 100, '2026-10-01 11:01:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 200);
        }
        foreach (range(1, 30) as $i) {
            $this->sample('request', 'GET a', 300, '2026-10-01 11:06:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), $i <= 3 ? 500 : 200);
        }
        foreach (range(1, 5) as $i) {
            $this->sample('request', 'GET a', 50, '2026-10-01 12:01:0'.$i, 200);
        }

        $aggregator = new Aggregator;
        $aggregator->aggregate(Carbon::parse('2026-10-01 11:00:00'), Carbon::parse('2026-10-01 11:05:00'));
        $aggregator->aggregate(Carbon::parse('2026-10-01 11:05:00'), Carbon::parse('2026-10-01 11:10:00'));
        $aggregator->aggregate(Carbon::parse('2026-10-01 12:00:00'), Carbon::parse('2026-10-01 12:05:00'));
        // Outside the 24 buckets (the oldest starts at 13:00 yesterday).
        $this->sample('request', 'GET a', 5000, '2026-09-30 12:01:00', 200);
        $aggregator->aggregate(Carbon::parse('2026-09-30 12:00:00'), Carbon::parse('2026-09-30 12:05:00'));

        $series = $this->telemetry()->requestSeries();

        $this->assertCount(24, $series['labels']);
        $this->assertSame('13:00', $series['labels'][0]);
        $this->assertSame('11:00', $series['labels'][22]);
        $this->assertSame('12:00', $series['labels'][23]);

        // 40 requests over a full hour; 3 errors.
        $this->assertSame(round(40 / 60, 2), $series['requests'][22]);
        $this->assertSame(round(3 / 60, 2), $series['errors'][22]);
        // Count-weighted across the two windows: (10·100 + 30·300) / 40.
        $this->assertSame(250, $series['p95'][22]);
        $this->assertSame(250, $series['p50'][22]);

        // The current bucket is covered only up to 12:05: 5 requests in 5 minutes.
        $this->assertSame(1.0, $series['requests'][23]);
        $this->assertSame(50, $series['p95'][23]);

        // No requests: a gap, not a zero.
        $this->assertNull($series['p95'][0]);
        $this->assertSame(0.0, $series['requests'][0]);
    }

    public function test_job_series(): void
    {
        $this->sample('job', 'App\Jobs\A', 10, '2026-10-01 12:01:00', 200);
        $this->sample('job', 'App\Jobs\A', 10, '2026-10-01 12:02:00', 500);
        $this->sample('job', 'App\Jobs\A', 10, '2026-10-01 12:03:00');
        (new Aggregator)->aggregate(Carbon::parse('2026-10-01 12:00:00'), Carbon::parse('2026-10-01 12:05:00'));

        $series = $this->telemetry()->jobSeries();

        $this->assertCount(24, $series['labels']);
        $this->assertSame(2, $series['processed'][23]);
        $this->assertSame(1, $series['failed'][23]);
        $this->assertSame(0, $series['processed'][0]);
    }

    public function test_window_and_buckets_follow_the_config(): void
    {
        config()->set('admin-pulse.dashboard.window_hours', 6);
        config()->set('admin-pulse.dashboard.buckets', 12);
        $this->sample('request', 'GET a', 10, '2026-10-01 12:01:00', 200);
        (new Aggregator)->aggregate(Carbon::parse('2026-10-01 12:00:00'), Carbon::parse('2026-10-01 12:05:00'));

        $series = $this->telemetry()->requestSeries();

        // 30-minute buckets ending with the one holding 12:30.
        $this->assertCount(12, $series['labels']);
        $this->assertSame('07:00', $series['labels'][0]);
        $this->assertSame('12:30', $series['labels'][11]);
        $this->assertSame(10, $series['p95'][10]);
    }
}
