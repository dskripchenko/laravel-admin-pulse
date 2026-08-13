<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests;

use Dskripchenko\LaravelAdmin\Testing\PackageTestCase;
use Dskripchenko\LaravelAdminPulse\AdminPulseServiceProvider;

abstract class TestCase extends PackageTestCase
{
    protected function additionalProviders(): array
    {
        return [AdminPulseServiceProvider::class];
    }

    protected function defineAdditionalEnvironment($app): void
    {
        // sample_rate = 1.0 so that the tests write samples deterministically.
        $app['config']->set('admin-pulse.sample_rate.request', 1.0);
    }
}
