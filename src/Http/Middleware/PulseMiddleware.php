<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Http\Middleware;

use Closure;
use Dskripchenko\LaravelAdminPulse\Services\Sampler;
use Illuminate\Http\Request;
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
 * sample itself and the health endpoints). The persistence happens in
 * terminate(), after the response has gone to the client, and does not affect
 * the request.
 */
final class PulseMiddleware
{
    /** @var array<string, float> [route_path => start_time_µs] */
    private array $startTimes = [];

    public function __construct(private readonly Sampler $sampler) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('admin-pulse.enabled', true)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        if ($this->isIgnored($request)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $key = $this->buildKey($request);
        $this->startTimes[$key] = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    /**
     * The persistence happens in terminate() and does not block the response.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! (bool) config('admin-pulse.enabled', true)) {
            return;
        }

        if ($this->isIgnored($request)) {
            return;
        }

        if (! $this->sampler->shouldSample('request')) {
            return;
        }

        $key = $this->buildKey($request);
        $start = $this->startTimes[$key] ?? null;
        if ($start === null) {
            return;
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);
        $this->sampler->record(
            kind: 'request',
            key: $key,
            durationMs: $durationMs,
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

    private function buildKey(Request $request): string
    {
        $route = $request->route();
        if ($route instanceof \Illuminate\Routing\Route) {
            return $request->method().' '.$route->uri();
        }

        return $request->method().' '.$request->path();
    }
}
