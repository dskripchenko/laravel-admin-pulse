<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests;

use Dskripchenko\LaravelAdmin\Testing\PackageTestCase;
use Dskripchenko\LaravelAdminPulse\AdminPulseServiceProvider;
use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Illuminate\Support\Carbon;

abstract class TestCase extends PackageTestCase
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function sample(
        string $kind,
        string $key,
        int $durationMs,
        string $at,
        ?int $status = null,
        ?string $label = null,
        ?array $meta = null,
    ): PulseSample {
        return PulseSample::query()->create([
            'kind' => $kind,
            'key' => $key,
            'label' => $label,
            'duration_ms' => $durationMs,
            'status_code' => $status,
            'meta' => $meta,
            'sampled_at' => Carbon::parse($at),
        ]);
    }

    protected function additionalProviders(): array
    {
        return [AdminPulseServiceProvider::class];
    }

    protected function defineAdditionalEnvironment($app): void
    {
        // sample_rate = 1.0 so that the tests write samples deterministically.
        $app['config']->set('admin-pulse.sample_rate.request', 1.0);
        $app['config']->set('admin-pulse.sample_rate.query', 1.0);
        // Every assertion sees the data as seeded, not a payload cached by
        // an earlier one.
        $app['config']->set('admin-pulse.dashboard.cache_seconds', 0);
    }
}
