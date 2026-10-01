<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse;

use Composer\InstalledVersions;
use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Menu\MenuNode;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;
use Dskripchenko\LaravelAdminPulse\Resources\PulseSampleResource;
use Dskripchenko\LaravelAdminPulse\Screens\TelemetryDashboardScreen;
use Dskripchenko\LaravelAdminPulse\Status\PulseStatusIndicator;
use Dskripchenko\LaravelAdminPulse\Support\PulseAccess;

final class AdminPulsePlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'pulse';
    }

    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('dskripchenko/laravel-admin-pulse') ?? 'dev';
    }

    public function register(): void {}

    public function boot(Admin $admin): void
    {
        $admin->resources([PulseSampleResource::class]);

        if ((bool) config('admin-pulse.dashboard.enabled', true)) {
            $admin->screen(TelemetryDashboardScreen::class);

            // Dashboards are not part of the automatic menu, so the entry is
            // added explicitly, next to the samples list. The label comes
            // from the screen's name(), translated per request.
            $admin->menu()->add(
                MenuNode::dashboard(TelemetryDashboardScreen::slug())
                    ->group('Системные')
                    ->icon('activity')
                    ->permissions(PulseAccess::PERMISSION),
            );
        }

        if ((bool) config('admin-pulse.indicator.enabled', true)) {
            $admin->statusIndicators([PulseStatusIndicator::class]);
        }

        $admin->permissions(
            ItemPermission::group(__('Системные'))
                ->addPermission(PulseAccess::PERMISSION, __('Телеметрия: просмотр')),
        );
    }
}
