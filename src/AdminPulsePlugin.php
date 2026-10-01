<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse;

use Composer\InstalledVersions;
use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;
use Dskripchenko\LaravelAdminPulse\Resources\PulseSampleResource;

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

        $admin->permissions(
            ItemPermission::group(__('Системные'))
                ->addPermission('admin.system.pulse.view', __('Телеметрия: просмотр')),
        );
    }
}
