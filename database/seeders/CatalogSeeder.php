<?php

namespace Database\Seeders;

use App\Models\AffiliateCatalog;
use App\Models\AffiliateFieldDefinition;
use App\Models\TrafficCatalog;
use App\Models\TrafficFieldDefinition;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->AffiliateNetworks();
        $this->TrafficSources();
    }

    public function AffiliateNetworks(): void
    {
        $this->Yieldkit();
        $this->Oponia();
    }

    public function TrafficSources(): void
    {
        $this->PropellerAds();
        $this->HilltopAds();
        $this->Popcash();
    }

    protected function Yieldkit(): void
    {
        $yieldkit = AffiliateCatalog::updateOrCreate(
            ['slug' => 'yieldkit'],
            [
                'name' => 'Yieldkit',
                'shortcut' => 'yk',
                'affiliate_token' => 'yk_tag',
                'offer_mode' => 'static',
                'commission_mode' => 'absolute',
                'merchant_id_label' => 'Advertiser ID',
                'blog_redirect_rate' => 10,
            ]
        );

        AffiliateFieldDefinition::updateOrCreate(
            [
                'affiliate_catalog_id' => $yieldkit->id,
                'field_key' => 'api_key',
            ],
            [
                'label' => 'API Key',
            ]
        );

        AffiliateFieldDefinition::updateOrCreate(
            [
                'affiliate_catalog_id' => $yieldkit->id,
                'field_key' => 'api_secret',
            ],
            [
                'label' => 'API Secret',
            ]
        );

        AffiliateFieldDefinition::updateOrCreate(
            [
                'affiliate_catalog_id' => $yieldkit->id,
                'field_key' => 'site_id',
            ],
            [
                'label' => 'Site ID',
            ]
        );
    }

    protected function Oponia(): void
    {
        $oponia = AffiliateCatalog::updateOrCreate(
            ['slug' => 'oponia'],
            [
                'name' => 'Oponia',
                'shortcut' => 'op',
                'affiliate_token' => 'placementId',
                'offer_mode' => 'static',
                'commission_mode' => 'absolute',
                'merchant_id_label' => 'Shop ID',
                'blog_redirect_rate' => 10,
            ]
        );

        AffiliateFieldDefinition::updateOrCreate(
            [
                'affiliate_catalog_id' => $oponia->id,
                'field_key' => 'api_key',
            ],
            [
                'label' => 'API Key',
            ]
        );

        AffiliateFieldDefinition::updateOrCreate(
            [
                'affiliate_catalog_id' => $oponia->id,
                'field_key' => 'publisherId',
            ],
            [
                'label' => 'Publisher ID',
            ]
        );
    }

    protected function PropellerAds(): void
    {
        $propellerAds = TrafficCatalog::updateOrCreate(
            ['slug' => 'propellerads'],
            [
                'name' => 'PropellerAds',
                'shortcut' => 'pro',
            ]
        );

        TrafficFieldDefinition::updateOrCreate(
            [
                'traffic_catalog_id' => $propellerAds->id,
                'field_key' => 'api_key',
            ],
            [
                'label' => 'API Key',
            ]
        );
    }

    protected function HilltopAds(): void
    {
        $hilltopAds = TrafficCatalog::updateOrCreate(
            ['slug' => 'hilltopads'],
            [
                'name' => 'HilltopAds',
                'shortcut' => 'ht',
            ]
        );

        TrafficFieldDefinition::updateOrCreate(
            [
                'traffic_catalog_id' => $hilltopAds->id,
                'field_key' => 'key',
            ],
            [
                'label' => 'API Key',
            ]
        );
    }

    protected function Popcash(): void
    {
        $popcash = TrafficCatalog::updateOrCreate(
            ['slug' => 'popcash'],
            [
                'name' => 'Popcash',
                'shortcut' => 'pop',
            ]
        );

        TrafficFieldDefinition::updateOrCreate(
            [
                'traffic_catalog_id' => $popcash->id,
                'field_key' => 'apikey',
            ],
            [
                'label' => 'API Key',
            ]
        );
    }
}
