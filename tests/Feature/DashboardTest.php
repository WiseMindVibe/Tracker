<?php

use App\Models\Campaign;
use App\Models\Company;
use App\Models\TrafficAccount;
use App\Models\TrafficCatalog;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('guests cannot access tracker modules', function () {
    $this->get('/m/blogs')->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('campaigns receive a uuid and fallback url when created without them', function () {
    $company = Company::factory()->create();
    $trafficCatalog = TrafficCatalog::factory()->create();
    $trafficAccount = TrafficAccount::factory()->create([
        'company_id' => $company->id,
        'traffic_catalog_id' => $trafficCatalog->id,
    ]);

    $campaign = Campaign::factory()->create([
        'traffic_account_id' => $trafficAccount->id,
        'fallback_url' => null,
    ]);

    expect($campaign->uuid)->not->toBeEmpty()
        ->and($campaign->fallback_url)->toBe('https://google.com');
});
