<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests\Feature;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdminPulse\Status\PulseStatusIndicator;
use Dskripchenko\LaravelAdminPulse\Tests\TestCase;

final class DisabledSurfacesTest extends TestCase
{
    protected function defineAdditionalEnvironment($app): void
    {
        parent::defineAdditionalEnvironment($app);
        $app['config']->set('admin-pulse.dashboard.enabled', false);
        $app['config']->set('admin-pulse.indicator.enabled', false);
    }

    public function test_dashboard_and_indicator_can_be_turned_off(): void
    {
        /** @var Admin $admin */
        $admin = app(Admin::class);

        $this->assertArrayNotHasKey('telemetry', $admin->getScreens());
        $this->assertNotContains(PulseStatusIndicator::class, $admin->getStatusIndicators());

        $keys = array_map(static fn ($node): string => $node->key(), $admin->menu()->roots());
        $this->assertNotContains('dashboard.telemetry', $keys);
    }
}
