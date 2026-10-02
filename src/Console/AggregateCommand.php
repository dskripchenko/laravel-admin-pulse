<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Console;

use Dskripchenko\LaravelAdminPulse\Services\Aggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * `php artisan admin:pulse:aggregate [--minutes=5] [--hours=0]`
 *
 * Run it from the scheduler every five minutes:
 *   $schedule->command('admin:pulse:aggregate')->everyFiveMinutes();
 *
 * By default it aggregates the last full window. `--hours=N` catches up: it
 * aggregates every full window of the last N hours — the windows missed while
 * the scheduler was down, or samples imported after the fact. A window
 * aggregated again replaces its rows, so the catch-up is safe to repeat.
 */
final class AggregateCommand extends Command
{
    protected $signature = 'admin:pulse:aggregate
        {--minutes=5 : Window size in minutes}
        {--hours=0 : Aggregate every full window of the last N hours, not only the last one}';

    protected $description = 'Aggregate raw pulse samples into percentile metrics';

    public function handle(Aggregator $aggregator): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $hours = max(0, (int) $this->option('hours'));

        $to = Carbon::now()->floor("{$minutes}minutes");
        $from = $to->copy()->subMinutes($minutes);

        if ($hours === 0) {
            $written = $aggregator->aggregate($from, $to);
            $this->info("Wrote $written aggregate row(s) for window [$from, $to)");

            return self::SUCCESS;
        }

        $earliest = $to->copy()->subHours($hours);
        $windows = 0;
        $written = 0;
        for ($start = $earliest->copy(); $start < $to; $start->addMinutes($minutes)) {
            $written += $aggregator->aggregate($start->copy(), $start->copy()->addMinutes($minutes));
            $windows++;
        }

        $this->info("Wrote $written aggregate row(s) for $windows window(s) in [$earliest, $to)");

        return self::SUCCESS;
    }
}
