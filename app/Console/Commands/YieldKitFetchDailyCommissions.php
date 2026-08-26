<?php

namespace App\Console\Commands;

use App\Services\Affiliates\YieldKit\YieldKitCommissionImporter;
use Illuminate\Console\Command;

class YieldKitFetchDailyCommissions extends Command
{
    /**
     * php artisan yieldkit:fetch-daily-commissions
     */
    protected $signature = 'yieldkit:fetch-daily-commissions {--type=sales}';

    protected $description = 'Fetch the last day (with a small overlap) of YieldKit commissions and upsert into conversions_events.';

    public function handle(YieldKitCommissionImporter $importer): int
    {
        $type = (string) $this->option('type');

        $this->info("Fetching YieldKit '{$type}' commissions (delta=2)...");

        // delta=2, not 1: gives a 1-day safety overlap so a commission
        // that changed status (CONFIRMED/DECLINED) after yesterday's run
        // still gets picked up. updateOrCreate makes re-processing the
        // overlap harmless — it just updates the same row again.
        $count = $importer->importByDelta(2, $type);

        $this->info("Done. Imported/updated {$count} rows.");

        return self::SUCCESS;
    }
}
