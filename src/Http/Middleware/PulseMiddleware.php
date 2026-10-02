<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Http\Middleware;

use Closure;
use Dskripchenko\LaravelAdminPulse\Services\Sampler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * The middleware that samples the request metrics.
 *
 * A host wires it into config('admin.middleware.api') / .web or into its own
 * RouteServiceProvider:
 *
 *     Route::middleware('pulse')->group(...);
 *
 * It ignores the routes listed in `admin-pulse.ignore_routes` (so as not to
 * sample itself and the health endpoints).
 *
 * The sample is written from an application `terminating` callback that
 * handle() registers, after the response has gone to the client. Not from the
 * middleware's own terminate(): the kernel calls that on a fresh instance of
 * the middleware, which knows nothing of the start time, and only for the
 * middleware of the matched route — a group run inside another pipeline (the
 * admin API runs `web` inside laravel-api's version pipeline) never gets it.
 */
final class PulseMiddleware
{
    public function __construct(
        private readonly Sampler $sampler,
        private readonly Application $app,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('admin-pulse.enabled', true) || $this->isIgnored($request)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $this->app->terminating(function () use ($request, $response, $start): void {
            $this->record($request, $response, $start);
        });

        return $response;
    }

    private function record(Request $request, Response $response, float $start): void
    {
        if (! $this->sampler->shouldSample('request')) {
            return;
        }

        $this->sampler->record(
            kind: 'request',
            key: $this->buildKey($request),
            durationMs: (int) ((microtime(true) - $start) * 1000),
            label: $request->method(),
            statusCode: $response->getStatusCode(),
            meta: [
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
            ],
        );
    }

    private function isIgnored(Request $request): bool
    {
        /** @var array<int, mixed> $patterns */
        $patterns = (array) config('admin-pulse.ignore_routes', []);
        foreach ($patterns as $pattern) {
            if (! is_string($pattern)) {
                continue;
            }
            if ($request->is(ltrim($pattern, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * "METHOD uri" of the matched route, with the parameters that name the
     * endpoint (`admin-pulse.key_parameters`) filled in. A generic route such
     * as laravel-api's `api/{version}/{controller}/{action}` would otherwise
     * put the whole API under one key; a record parameter (`{product}`) stays
     * a template, so the key does not grow with the number of records.
     */
    private function buildKey(Request $request): string
    {
        $route = $request->route();
        if (! $route instanceof Route) {
            return $request->method().' '.$request->path();
        }

        $uri = $route->uri();
        /** @var array<int, mixed> $names */
        $names = (array) config('admin-pulse.key_parameters', ['version', 'controller', 'action']);
        foreach ($names as $name) {
            if (! is_string($name) || ! $route->hasParameter($name)) {
                continue;
            }
            $value = $route->parameter($name);
            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }
            $uri = preg_replace('/\{'.preg_quote($name, '/').'\??\}/', (string) $value, $uri) ?? $uri;
        }

        return $request->method().' '.$uri;
    }
}
