<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests\Feature;

use Dskripchenko\LaravelAdmin\Testing\Concerns\ActsAsAdmin;
use Dskripchenko\LaravelAdminPulse\Status\PulseStatusIndicator;
use Dskripchenko\LaravelAdminPulse\Tests\TestCase;
use Illuminate\Support\Carbon;

final class StatusIndicatorTest extends TestCase
{
    use ActsAsAdmin;

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

    private function seedRequests(int $ok, int $errors, string $at = '2026-10-01 12:25:00'): void
    {
        for ($i = 0; $i < $ok; $i++) {
            $this->sample('request', 'GET a', 10, $at, 200);
        }
        for ($i = 0; $i < $errors; $i++) {
            $this->sample('request', 'GET a', 10, $at, 500);
        }
    }

    private function state(): array
    {
        return app(PulseStatusIndicator::class)->state();
    }

    public function test_silent_with_too_few_requests(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedRequests(5, 5);

        $this->assertSame('ok', $this->state()['status']);
    }

    public function test_warning_and_error_thresholds(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedRequests(90, 10);

        $state = $this->state();
        $this->assertSame('warning', $state['status']);
        $this->assertSame('5xx errors: 10%', $state['label']);
        $this->assertSame('/dashboard/telemetry', $state['url']);

        $this->seedRequests(0, 20);
        $this->assertSame('error', $this->state()['status']);
    }

    public function test_only_the_recent_window_counts(): void
    {
        $this->actingAsSuperAdmin();
        // 20 minutes ago: outside the 15-minute window.
        $this->seedRequests(0, 50, '2026-10-01 12:10:00');
        $this->seedRequests(30, 0);

        $this->assertSame('ok', $this->state()['status']);
    }

    public function test_silent_for_users_without_the_permission(): void
    {
        $this->actingAsAdmin(permissions: ['admin.users.view']);
        $this->seedRequests(0, 50);

        $this->assertSame('ok', $this->state()['status']);
    }

    public function test_served_by_the_status_endpoint(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedRequests(0, 30);

        $indicators = $this->getJson('/api/admin/system/status')->assertOk()->json('payload.indicators');
        $pulse = collect($indicators)->firstWhere('key', 'admin.pulse');

        $this->assertNotNull($pulse);
        $this->assertSame('error', $pulse['status']);
    }
}
