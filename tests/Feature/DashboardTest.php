<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests\Feature;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Testing\Concerns\ActsAsAdmin;
use Dskripchenko\LaravelAdminPulse\Screens\TelemetryDashboardScreen;
use Dskripchenko\LaravelAdminPulse\Status\PulseStatusIndicator;
use Dskripchenko\LaravelAdminPulse\Tests\TestCase;
use Dskripchenko\LaravelAdminPulse\Widgets\ErrorRateWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\ExceptionCountWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\RequestCountWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\ResponseTimeP95Widget;
use Dskripchenko\LaravelAdminPulse\Widgets\ResponseTimeWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\SlowRoutesWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\ThroughputWidget;
use Illuminate\Support\Carbon;

final class DashboardTest extends TestCase
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

    /**
     * @return array<string, mixed>|null
     */
    private function telemetryDashboard(): ?array
    {
        $dashboards = $this->getJson('/api/admin/system/manifest')
            ->assertOk()
            ->json('payload.dashboards');

        foreach ((array) $dashboards as $dashboard) {
            if (($dashboard['slug'] ?? null) === 'telemetry') {
                return $dashboard;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @return array<string, array<string, mixed>>
     */
    private function widgetsBySlug(array $dashboard): array
    {
        $out = [];
        foreach ($dashboard['widgets'] as $widget) {
            $out[$widget['slug']] = $widget;
        }

        return $out;
    }

    public function test_dashboard_screen_menu_and_indicator_are_registered(): void
    {
        /** @var Admin $admin */
        $admin = app(Admin::class);

        $this->assertSame(TelemetryDashboardScreen::class, $admin->getScreens()['telemetry'] ?? null);
        $this->assertContains(PulseStatusIndicator::class, $admin->getStatusIndicators());

        $keys = array_map(static fn ($node): string => $node->key(), $admin->menu()->roots());
        $this->assertContains('dashboard.telemetry', $keys);
    }

    public function test_menu_entry_points_at_the_dashboard_and_carries_the_permission(): void
    {
        $this->actingAsSuperAdmin();

        $items = $this->getJson('/api/admin/system/menu')->assertOk()->json('payload.items');
        $entry = collect($items)->firstWhere('key', 'dashboard.telemetry');

        $this->assertNotNull($entry);
        $this->assertSame('/dashboard/telemetry', $entry['url']);
        $this->assertSame(['admin.system.pulse.view'], $entry['permissions']);
        $this->assertSame('Telemetry', $entry['label']);
    }

    public function test_permitted_user_gets_every_widget_with_data(): void
    {
        $this->sample('request', 'GET api/orders', 120, '2026-10-01 12:00:00', 200);
        $this->sample('request', 'GET api/orders', 80, '2026-10-01 12:01:00', 500);

        $this->actingAsAdmin(permissions: ['admin.system.pulse.view']);

        $dashboard = $this->telemetryDashboard();
        $this->assertNotNull($dashboard);
        $this->assertSame('Telemetry', $dashboard['label']);

        $widgets = $this->widgetsBySlug($dashboard);
        $this->assertSame([
            'admin.pulse.request-count',
            'admin.pulse.error-rate',
            'admin.pulse.p95',
            'admin.pulse.exception-count',
            'admin.pulse.throughput',
            'admin.pulse.response-time',
            'admin.pulse.slow-routes',
            'admin.pulse.slow-queries',
            'admin.pulse.top-exceptions',
            'admin.pulse.jobs',
        ], array_keys($widgets));

        $this->assertSame('stats', $widgets['admin.pulse.error-rate']['type']);
        $this->assertSame(50.0, (float) $widgets['admin.pulse.error-rate']['data']['stats'][0]['value']);
        $this->assertSame('negative', $widgets['admin.pulse.error-rate']['data']['tone']);

        $this->assertSame('table', $widgets['admin.pulse.slow-routes']['type']);
        $this->assertSame('GET api/orders', $widgets['admin.pulse.slow-routes']['data']['rows'][0]['route']);
        $this->assertSame(
            ['route', 'count', 'avg_ms', 'p95_ms', 'max_ms', 'errors'],
            array_column($widgets['admin.pulse.slow-routes']['data']['columns'], 'name'),
        );

        $this->assertSame('chart', $widgets['admin.pulse.response-time']['type']);
        $this->assertSame('line', $widgets['admin.pulse.response-time']['data']['chartType']);
        $this->assertSame('admin.system.pulse.view', $widgets['admin.pulse.response-time']['permission']);
    }

    public function test_user_without_the_permission_gets_no_widgets(): void
    {
        $this->sample('request', 'GET api/secret', 120, '2026-10-01 12:00:00', 200);

        $this->actingAsAdmin(permissions: ['admin.users.view']);

        $dashboard = $this->telemetryDashboard();
        $this->assertNotNull($dashboard);
        $this->assertSame([], $dashboard['widgets']);
    }

    public function test_widget_placed_elsewhere_sends_nothing_without_the_permission(): void
    {
        $this->sample('request', 'GET api/secret', 120, '2026-10-01 12:00:00', 200);

        $this->actingAsAdmin(permissions: ['admin.users.view']);

        $this->assertSame([], SlowRoutesWidget::make()->data()['rows']);
        $this->assertSame([], ThroughputWidget::make()->data()['labels']);
        $this->assertSame([], RequestCountWidget::make()->data()['stats']);
    }

    public function test_widgets_have_an_empty_state(): void
    {
        $this->actingAsSuperAdmin();

        $this->assertSame([], SlowRoutesWidget::make()->data()['rows']);
        $this->assertSame([], ResponseTimeWidget::make()->data()['labels']);
        $this->assertSame([], ResponseTimeWidget::make()->data()['datasets']);
        $this->assertSame('—', ErrorRateWidget::make()->data()['stats'][0]['value']);
        $this->assertSame('—', ResponseTimeP95Widget::make()->data()['stats'][0]['value']);
        $this->assertSame(0, ExceptionCountWidget::make()->data()['stats'][0]['value']);
    }

    public function test_data_is_idempotent(): void
    {
        $this->actingAsSuperAdmin();
        $widget = RequestCountWidget::make();

        $widget->data();

        $this->assertCount(1, $widget->data()['stats']);
    }

    public function test_dashboard_widgets_endpoint_refreshes_the_dashboard(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/admin/dashboard/widgets?key=telemetry&period=7d')
            ->assertOk()
            ->assertJsonCount(10, 'payload.widgets');
    }

    public function test_english_labels(): void
    {
        app()->setLocale('en');
        $this->actingAsSuperAdmin();

        $this->assertSame('Telemetry', (new TelemetryDashboardScreen)->name());
        $this->assertSame('Slowest routes', SlowRoutesWidget::make()->toArray()['title']);
    }
}
