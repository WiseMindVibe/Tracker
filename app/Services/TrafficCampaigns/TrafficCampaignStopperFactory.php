<?php

namespace App\Services\TrafficCampaigns;

use App\Contracts\TrafficCampaignStopper;

class TrafficCampaignStopperFactory
{
    public function make(string $slug): ?TrafficCampaignStopper
    {
        return match (strtolower($slug)) {
            'propeller', 'propellerads' => app(PropellerAdsCampaignStopper::class),
            default => null,
        };
    }
}
