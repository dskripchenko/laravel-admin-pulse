<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Support;

use Dskripchenko\LaravelAdmin\Panel\Panels;
use Illuminate\Support\Facades\Auth;

/**
 * Whether the current panel user may see telemetry.
 *
 * The widgets ask it themselves rather than relying on the dashboard alone:
 * a dashboard's widget payloads are computed while the panel manifest is
 * built, for every signed-in user, so a widget that only declared a
 * permission would still have run its queries and shipped its numbers to
 * someone who may not see them.
 */
final class PulseAccess
{
    public const PERMISSION = 'admin.system.pulse.view';

    public static function allowed(): bool
    {
        $user = Auth::guard(Panels::currentGuard())->user();
        if ($user === null) {
            return false;
        }

        // The same rule the core applies to widget permissions: a user model
        // without the access trait is not subject to the role matrix.
        if (! method_exists($user, 'hasAccess')) {
            return true;
        }

        return (bool) $user->hasAccess(self::PERMISSION);
    }
}
