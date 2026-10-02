<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests\Feature;

use Dskripchenko\LaravelAdminPulse\Models\PulseAggregate;
use Dskripchenko\LaravelAdminPulse\Tests\TestCase;
use Illuminate\Support\Carbon;

final class AggregateCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_by_default_only_the_last_full_window_is_aggregated(): void
    {
        Carbon::setTestNow('2026-10-02 12:07:30');
        $this->sample('request', 'GET a', 10, '2026-10-02 12:02:00', 200);
        $this->sample('request', 'GET a', 20, '2026-10-02 11:31:00', 200);

        $this->artisan('admin:pulse:aggregate')->assertSuccessful();

        $rows = PulseAggregate::query()->where('bucket', 'requests')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('2026-10-02 12:00:00', $rows[0]->period_start->format('Y-m-d H:i:s'));
    }

    public function test_hours_catches_up_every_missed_window_and_can_be_repeated(): void
    {
        Carbon::setTestNow('2026-10-02 12:07:30');
        $this->sample('request', 'GET a', 10, '2026-10-02 12:02:00', 200);
        $this->sample('request', 'GET a', 20, '2026-10-02 11:31:00', 500);
        $this->sample('request', 'GET b', 30, '2026-10-02 10:06:00', 200);
        $this->sample('job', 'App\Jobs\X', 0, '2026-10-02 11:31:10', 500);
        // Outside the two hours asked for.
        $this->sample('request', 'GET a', 40, '2026-10-02 09:59:00', 200);
        // In the current, unfinished window.
        $this->sample('request', 'GET a', 50, '2026-10-02 12:06:00', 200);

        $this->artisan('admin:pulse:aggregate', ['--hours' => 2])->assertSuccessful();
        $this->artisan('admin:pulse:aggregate', ['--hours' => 2])->assertSuccessful();

        $requests = PulseAggregate::query()->where('bucket', 'requests')->orderBy('period_start')->get();
        $this->assertSame(
            ['2026-10-02 10:05:00', '2026-10-02 11:30:00', '2026-10-02 12:00:00'],
            $requests->map(fn (PulseAggregate $row) => $row->period_start->format('Y-m-d H:i:s'))->all(),
        );
        $this->assertSame(1, $requests[1]->metrics['errors']);

        $jobs = PulseAggregate::query()->where('bucket', 'jobs')->sole();
        $this->assertSame(['count' => 1, 'failed' => 1], $jobs->metrics);
    }
}
