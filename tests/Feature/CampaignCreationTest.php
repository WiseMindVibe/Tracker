<?php

use App\Models\Campaign;
use App\Models\Company;
use App\Models\TrafficAccount;
use App\Models\TrafficCatalog;
use App\Models\User;
use Illuminate\Support\Str;

test('campaign creation preserves the uuid shown on the create form', function () {
    $company = Company::factory()->create();
    $trafficCatalog = TrafficCatalog::factory()->create();
    $trafficAccount = TrafficAccount::factory()->create([
        'company_id' => $company->id,
        'traffic_catalog_id' => $trafficCatalog->id,
    ]);
    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'is_tester' => '0',
        'name' => 'Test campaign',
        'country' => 'US',
        'traffic_account_id' => (string) $trafficAccount->id,
        'fallback_url' => 'https://google.com',
        'status' => 'active',
        'offers' => [],
        'trafficIds' => [],
    ];

    $this->actingAs(User::factory()->create())
        ->post('/m/campaigns', $payload)
        ->assertRedirect('/m/campaigns');

    $campaign = Campaign::query()->firstOrFail();

    $this->put("/m/campaigns/{$campaign->id}", [
        ...$payload,
        'uuid' => (string) Str::uuid(),
    ])->assertRedirect('/m/campaigns');

    expect($campaign->refresh()->uuid)->toBe($uuid);
});
