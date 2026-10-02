<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests\Feature;

use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Dskripchenko\LaravelAdminPulse\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/**
 * The middleware through real HTTP requests: the kernel resolves a fresh
 * instance of it for terminate(), so a sample must not depend on state kept
 * on the instance that ran handle().
 */
final class MiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware('pulse')->group(function (): void {
            Route::get('pulse-test/products/{product}', fn () => 'ok');
            Route::post('pulse-api/{version}/{controller}/{action}', fn () => response('fail', 500));
            Route::get('pulse-ignored', fn () => 'ok');
        });
    }

    public function test_a_request_writes_a_sample(): void
    {
        $this->get('/pulse-test/products/42')->assertOk();

        $sample = PulseSample::query()->sole();
        $this->assertSame('request', $sample->kind);
        $this->assertSame('GET pulse-test/products/{product}', $sample->key);
        $this->assertSame('GET', $sample->label);
        $this->assertSame(200, $sample->status_code);
        $this->assertArrayHasKey('memory_peak_mb', (array) $sample->meta);
    }

    public function test_the_endpoint_parameters_of_a_generic_route_are_filled_into_the_key(): void
    {
        $this->post('/pulse-api/admin/orders/search')->assertStatus(500);

        $sample = PulseSample::query()->sole();
        $this->assertSame('POST pulse-api/admin/orders/search', $sample->key);
        $this->assertSame(500, $sample->status_code);
    }

    public function test_the_key_parameters_are_configurable(): void
    {
        config(['admin-pulse.key_parameters' => ['product']]);

        $this->get('/pulse-test/products/42')->assertOk();

        $this->assertSame('GET pulse-test/products/42', PulseSample::query()->sole()->key);
    }

    public function test_ignored_routes_and_a_disabled_pack_write_nothing(): void
    {
        config(['admin-pulse.ignore_routes' => ['pulse-ignored']]);
        $this->get('/pulse-ignored')->assertOk();

        config(['admin-pulse.enabled' => false]);
        $this->get('/pulse-test/products/1')->assertOk();

        $this->assertSame(0, PulseSample::query()->count());
    }

    public function test_the_sample_rate_applies(): void
    {
        config(['admin-pulse.sample_rate.request' => 0.0]);

        $this->get('/pulse-test/products/1')->assertOk();

        $this->assertSame(0, PulseSample::query()->count());
    }
}
