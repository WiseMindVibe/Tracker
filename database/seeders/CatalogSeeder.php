<?php

namespace Database\Seeders;

use App\Models\AffiliateCatalog;
use App\Models\AffiliateFieldDefinition;
use App\Models\TrafficCatalog;
use App\Models\TrafficFieldDefinition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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

    public function AffiliateNetworks()
    {
        $this->Yieldkit();
        $this->Oponia();
    }

    public function TrafficSources()
    {
        $this->PropellerAds();
        $this->HilltopAds();
        $this->Popcash();
    }

    protected function Yieldkit()
    {

        $yieldkit = AffiliateCatalog::factory()->create([
            'name' => 'Yieldkit',
            'slug' => 'yieldkit',
            'shortcut' => 'yk',
            'affiliate_token' => 'yk_tag',
            'offer_mode' => 'static',
            'commission_mode' => 'absolute',
            'merchant_id_label' => 'Advertiser ID',
            'blog_redirect_rate' => '10'
        ]);

        AffiliateFieldDefinition::factory()->create([
            'affiliate_catalog_id' => $yieldkit->id,
            'label' => 'API Key',
            'field_key' => 'api_key'
        ]);

        AffiliateFieldDefinition::factory()->create([
            'affiliate_catalog_id' => $yieldkit->id,
            'label' => 'API Secret',
            'field_key' => 'api_secret'
        ]);
        AffiliateFieldDefinition::factory()->create([
            'affiliate_catalog_id' => $yieldkit->id,
            'label' => 'Site ID',
            'field_key' => 'site_id'
        ]);
    }

    protected function Oponia()
    {

        $oponia = AffiliateCatalog::factory()->create([
            'name' => 'Oponia',
            'slug' => 'oponia',
            'shortcut' => 'op',
            'affiliate_token' => 'placementId',
            'offer_mode' => 'static',
            'commission_mode' => 'absolute',
            'merchant_id_label' => 'Shop ID',
            'blog_redirect_rate' => '10'
        ]);

        AffiliateFieldDefinition::factory()->create([
            'affiliate_catalog_id' => $oponia->id,
            'label' => 'API Key',
            'field_key' => 'api_key'
        ]);

        AffiliateFieldDefinition::factory()->create([
            'affiliate_catalog_id' => $oponia->id,
            'label' => 'Publisher ID',
            'field_key' => 'publisherId'
        ]);
    }

    protected function PropellerAds()
    {
        $propellerAds = TrafficCatalog::factory()->create([
            'name' => 'PropellerAds',
            'slug' => 'propellerads',
            'shortcut' => 'pro',
        ]);

        TrafficFieldDefinition::factory()->create([
            'traffic_catalog_id' => $propellerAds->id,
            'label' => 'API Key',
            'field_key' => 'api_key'
        ]);
    }

    protected function HilltopAds()
    {
        $HilltopAds = TrafficCatalog::factory()->create([
            'name' => 'HilltopAds',
            'slug' => 'hilltopads',
            'shortcut' => 'ht',
        ]);

        TrafficFieldDefinition::factory()->create([
            'traffic_catalog_id' => $HilltopAds->id,
            'label' => 'API Key',
            'field_key' => 'api_key'
        ]);
    }

    protected function Popcash()
    {
        $popcash = TrafficCatalog::factory()->create([
            'name' => 'Popcash',
            'slug' => 'popcash',
            'shortcut' => 'pop',
        ]);

        TrafficFieldDefinition::factory()->create([
            'traffic_catalog_id' => $popcash->id,
            'label' => 'API Key',
            'field_key' => 'api_key'
        ]);
    }
}
