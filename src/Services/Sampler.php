<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Services;

use Dskripchenko\LaravelAdminPulse\Models\PulseSample;
use Illuminate\Support\Carbon;

/**
 * The low-level sampler — it writes the samples into `admin_pulse_samples`.
 *
 * The sample rate (0..1) is applied in `shouldSample()` through mt_rand();
 * rate=1.0 writes everything, rate=0.1 writes 10%. The persistence is inline
 * (synchronous), but a host project may wrap it into a queued job for
 * non-blocking inserts.
 *
 * It is used by PulseMiddleware for the request metrics and by the host by hand
 * for the job/exception/cache metrics, through `record()`.
 */
final class Sampler
{
    /**
     * Should this sample pass the selection?
     */
    public function shouldSample(string $kind): bool
    {
        $rate = (float) config("admin-pulse.sample_rate.$kind", 1.0);
        if ($rate >= 1.0) {
            return true;
        }
        if ($rate <= 0.0) {
            return false;
        }

        return mt_rand(0, 999) < (int) ($rate * 1000);
    }

    /**
     * Write a sample into the database.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function record(
        string $kind,
        string $key,
        int $durationMs,
        ?string $label = null,
        ?int $statusCode = null,
        ?array $meta = null,
    ): void {
        PulseSample::query()->create([
            'kind' => $kind,
            'key' => $key,
            'label' => $label,
            'duration_ms' => $durationMs,
            'status_code' => $statusCode,
            'meta' => $meta,
            'sampled_at' => Carbon::now(),
        ]);
    }

    /**
     * The normalized SQL fingerprint — it replaces the literals with ?.
     *
     * A simple version: PCRE over the quoted strings and the numeric literals.
     * Production-grade normalization would need a full SQL parser.
     */
    public function fingerprintSql(string $sql): string
    {
        $sql = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", "'?'", $sql) ?? $sql;
        $sql = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '"?"', $sql) ?? $sql;
        $sql = preg_replace('/\b\d+(\.\d+)?\b/', '?', $sql) ?? $sql;

        return trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);
    }

    /**
     * A hash of the exception's signature (class + message + first stack line).
     */
    public function fingerprintException(\Throwable $e): string
    {
        $head = get_class($e).':'.$e->getMessage();
        $trace = $e->getFile().':'.$e->getLine();

        return substr(hash('xxh64', $head."\n".$trace), 0, 16);
    }
}
