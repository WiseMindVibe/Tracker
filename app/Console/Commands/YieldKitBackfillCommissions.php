<?php

namespace App\Console\Commands;

use App\Services\Affiliates\YieldKit\YieldKitCommissionImporter;
use Illuminate\Console\Command;

class YieldKitBackfillCommissions extends Command
{
    /**
     * php artisan yieldkit:backfill-commissions
     * php artisan yieldkit:backfill-commissions --days=365 --type=sales
     */
    protected $signature = 'yieldkit:backfill-commissions
        {--days=365 : How many days back to fetch}
        {--type=modified : Date type path segment, e.g. modified / sales}';
    protected $description = 'One-off backfill of YieldKit commissions into conversions_events.';

    public function handle(YieldKitCommissionImporter $importer): int
    {
        $days = (int) $this->option('days');
        $type = (string) $this->option('type');

        $end = now();
        $start = now()->subDays($days);

        $this->info("Backfilling YieldKit '{$type}' commissions: {$start->toDateString()} -> {$end->toDateString()}");

        // Chunked into monthly windows rather than one single year-long
        // request: keeps each request/response reasonably sized and keeps
        // pagination (and any retry-on-failure) manageable per chunk.
        $cursor = $start->copy();
        $total = 0;

        while ($cursor->lt($end)) {
            $chunkEnd = $cursor->copy()->addMonth()->min($end);

            $this->line("  fetching {$cursor->toDateString()} -> {$chunkEnd->toDateString()} ...");

            $count = $importer->importByDateRange($cursor, $chunkEnd, $type);
            $total += $count;

            $this->line("    imported/updated {$count} rows");

            $cursor = $chunkEnd;
        }

        $this->info("Done. Total imported/updated: {$total} rows.");

        return self::SUCCESS;
    }
}
