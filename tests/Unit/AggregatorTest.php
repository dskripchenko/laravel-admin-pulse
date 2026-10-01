<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests\Unit;

use Dskripchenko\LaravelAdminPulse\Models\PulseAggregate;
use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Dskripchenko\LaravelAdminPulse\Services\Aggregator;
use Dskripchenko\LaravelAdminPulse\Tests\TestCase;
use Illuminate\Support\Carbon;

final class AggregatorTest extends TestCase
{
    public function test_percentile_correctness(): void
    {
        $aggregator = new Aggregator;
        $sorted = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $this->assertSame(1, $aggregator->percentile($sorted, 0.0));
        $this->assertSame(10, $aggregator->percentile($sorted, 1.0));
        $this->assertSame(6, $aggregator->percentile($sorted, 0.50));
        $this->assertSame(10, $aggregator->percentile($sorted, 0.95));
    }

    public function test_percentile_empty_returns_zero(): void
    {
        $aggregator = new Aggregator;
        $this->assertSame(0, $aggregator->percentile([], 0.95));
    }

    public function test_aggregate_creates_route_percentile_rows(): void
    {
        $now = Carbon::now()->floor('5 minutes');
        $from = $now->copy()->subMinutes(5);
        $to = $now;

        // 10 samples per route, two routes
        for ($i = 1; $i <= 10; $i++) {
            PulseSample::query()->create([
                'kind' => 'request',
                'key' => 'GET /a',
                'label' => 'GET',
                'duration_ms' => $i * 10,
                'status_code' => 200,
                'meta' => null,
                'sampled_at' => $from->copy()->addSeconds($i),
            ]);
            PulseSample::query()->create([
                'kind' => 'request',
                'key' => 'GET /b',
                'label' => 'GET',
                'duration_ms' => $i * 100,
                'status_code' => 200,
                'meta' => null,
                'sampled_at' => $from->copy()->addSeconds($i),
            ]);
        }

        $aggregator = new Aggregator;
        $written = $aggregator->aggregate($from, $to);
        // one row per route plus the overall 'requests' row
        $this->assertSame(3, $written);

        $a = PulseAggregate::query()->where('key', 'GET /a')->first();
        $this->assertNotNull($a);
        $this->assertSame(10, $a->metrics['count']);
        $this->assertSame(100, $a->metrics['max']);
        $this->assertSame(10, $a->metrics['min']);
        $this->assertGreaterThan(0, $a->metrics['p95']);
        $this->assertSame('route.percentiles', $a->bucket);
    }

    public function test_aggregate_returns_zero_when_no_samples(): void
    {
        $aggregator = new Aggregator;
        $written = $aggregator->aggregate(Carbon::now()->subMinutes(5), Carbon::now());
        $this->assertSame(0, $written);
    }

    public function test_aggregate_writes_overall_request_and_job_buckets(): void
    {
        $from = Carbon::parse('2026-10-01 10:00:00');
        $to = Carbon::parse('2026-10-01 10:05:00');

        foreach ([100, 200, 300, 400] as $i => $ms) {
            $this->sample('request', 'GET /a', $ms, '2026-10-01 10:01:0'.$i, $i === 3 ? 503 : 200);
        }
        $this->sample('request', 'GET /b', 1000, '2026-10-01 10:02:00', 404);
        // Outside the window: the end is exclusive.
        $this->sample('request', 'GET /a', 9999, '2026-10-01 10:05:00', 500);

        $this->sample('job', 'App\\Jobs\\Send', 10, '2026-10-01 10:01:00', 200);
        $this->sample('job', 'App\\Jobs\\Send', 10, '2026-10-01 10:01:30', 500);
        $this->sample('job', 'App\\Jobs\\Send', 10, '2026-10-01 10:02:00');

        $written = (new Aggregator)->aggregate($from, $to);

        // two routes + requests + jobs
        $this->assertSame(4, $written);

        $all = PulseAggregate::query()->where('bucket', 'requests')->sole();
        $this->assertSame('*', $all->key);
        $this->assertSame(5, $all->metrics['count']);
        $this->assertSame(1, $all->metrics['errors']);
        $this->assertSame(300, $all->metrics['p50']);
        $this->assertSame(1000, $all->metrics['max']);

        $a = PulseAggregate::query()->where('bucket', 'route.percentiles')->where('key', 'GET /a')->sole();
        $this->assertSame(4, $a->metrics['count']);
        $this->assertSame(1, $a->metrics['errors']);
        $this->assertSame(400, $a->metrics['max']);

        $jobs = PulseAggregate::query()->where('bucket', 'jobs')->sole();
        $this->assertSame(['count' => 3, 'failed' => 1], $jobs->metrics);
    }

    public function test_aggregating_the_same_window_twice_replaces_its_rows(): void
    {
        $from = Carbon::parse('2026-10-01 10:00:00');
        $to = Carbon::parse('2026-10-01 10:05:00');
        $this->sample('request', 'GET /a', 100, '2026-10-01 10:01:00', 200);

        (new Aggregator)->aggregate($from, $to);
        (new Aggregator)->aggregate($from, $to);

        $this->assertSame(1, PulseAggregate::query()->where('bucket', 'requests')->count());
        $this->assertSame(1, PulseAggregate::query()->where('bucket', 'route.percentiles')->count());
    }
}
