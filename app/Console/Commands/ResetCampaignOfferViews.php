<?php

namespace App\Console\Commands;

use App\Models\CampaignOffer;
use Illuminate\Console\Command;

class ResetCampaignOfferViews extends Command
{
    protected $signature = 'campaigns:reset-current-views';

    protected $description = 'Reset current views for all campaign offers';

    public function handle(): int
    {
        $count = CampaignOffer::query()->update(['current_views' => 0]);

        $this->info("Reset current views for {$count} campaign offers.");

        return self::SUCCESS;
    }
}
