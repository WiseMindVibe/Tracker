<?php

use App\Models\AffiliateAccount;
use App\Models\AffiliateCatalog;
use App\Models\Blog;
use App\Models\Campaign;
use App\Models\Click;
use App\Models\Company;
use App\Models\ConversionEvent;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\TrafficAccount;
use App\Models\TrafficCatalog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('loads recent notifications in pages and honors the selected page size', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $trafficCatalog = TrafficCatalog::factory()->create();
    $trafficAccount = TrafficAccount::factory()->create([
        'company_id' => $company->id,
        'traffic_catalog_id' => $trafficCatalog->id,
    ]);
    $campaign = Campaign::factory()->create([
        'traffic_account_id' => $trafficAccount->id,
        'name' => 'Test campaign',
    ]);
    $affiliateCatalog = AffiliateCatalog::factory()->create([
        'commission_mode' => 'absolute',
        'slug' => 'test-affiliate',
    ]);
    $affiliateAccount = AffiliateAccount::factory()->create([
        'company_id' => $company->id,
        'affiliate_catalog_id' => $affiliateCatalog->id,
    ]);
    $blog = Blog::factory()->create(['company_id' => $company->id]);
    $offer = Offer::factory()->create([
        'affiliate_account_id' => $affiliateAccount->id,
        'blog_id' => $blog->id,
        'name' => 'Test offer',
    ]);
    $click = Click::factory()->create([
        'offer_id' => $offer->id,
        'campaign_id' => $campaign->id,
        'traffic_campaign_id' => null,
    ]);

    $notificationIds = collect(range(1, 17))->map(function (int $number) use ($affiliateCatalog, $click): int {
        $event = ConversionEvent::factory()->create([
            'click_id' => $click->id,
            'affiliate_catalog_id' => $affiliateCatalog->id,
            'commission_id' => "commission-{$number}",
            'created_at' => now()->subMinutes(17 - $number),
        ]);

        return Notification::factory()->create([
            'conversion_event_id' => $event->id,
            'is_read' => false,
            'created_at' => now()->subMinutes(17 - $number),
        ])->id;
    });

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertInertia(function (Assert $page) use ($notificationIds): void {
            $page->component('Notifications/index')
                ->where('perPage', 15)
                ->missing('notifications')
                ->missing('unreadCount')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->has('notifications.data', 15)
                    ->where('notifications.total', 17)
                    ->where('notifications.per_page', 15)
                    ->where('notifications.data.0.id', $notificationIds->last())
                    ->where('unreadCount', 17)
                );
        });

    $this->get(route('notifications.index', ['per_page' => 50]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('perPage', 50)
            ->loadDeferredProps(fn (Assert $page) => $page
                ->has('notifications.data', 17)
                ->where('notifications.per_page', 50)
            )
        );

    $this->get(route('notifications.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $page) => $page
                ->has('notifications.data', 2)
                ->where('notifications.data.0.id', $notificationIds->get(1))
            )
        );

    foreach (['Test offer', 'Test campaign'] as $search) {
        $this->get(route('notifications.index', ['search' => $search]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('search', $search)
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('notifications.total', 17)
                    ->where('notifications.data.0.id', $notificationIds->last())
                )
            );
    }
});
