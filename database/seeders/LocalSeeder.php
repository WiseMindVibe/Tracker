<?php

namespace Database\Seeders;

use App\Models\AffiliateCatalog;
use App\Models\Blog;
use App\Models\BlogBuffer;
use App\Models\Company;
use App\Models\TrafficCatalog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        User::factory()->create([
            'name' => 'Local Admin',
            'email' => 'local@admin.com',
            'password' => Hash::make('LocalAdmin1465'),
        ]);
*/
        $localComapny = Company::factory()->create([
            'name' => 'Local Comapny',
            'slug' => 'localcompany',
            'proxy_host' => null,
            'proxy_port' => null,
            'proxy_username' => null,
            'proxy_password' => null,
            'status' => 'active',
        ]);

        $localBlog = Blog::factory()->create([
            'company_id' => $localComapny->id,
            'domain' => 'http://blog.test',
            'main_geo' => 'US',
            'status' => 'active',
        ]);

        BlogBuffer::factory()->create([
            'blog_id' => $localBlog->id,
            'buffer_url' => 'http://buffer.test',
        ]);

        AffiliateCatalog::factory()->create([
            'name' => 'Local Affiliate',
            'slug' => 'local_affiliate',
            'shortcut' => 'LA',
            'affiliate_token' => 'la_token',
            'offer_mode' => 'static',
            'commission_mode' => 'absolute',
            'merchant_id_label' => 'Advertiser ID',
            'blog_redirect_rate' => 50,
        ]);

        TrafficCatalog::factory()->create([
            'name' => 'Local Traffic',
            'slug' => 'local_traffic',
            'shortcut' => 'LT',
        ]);
    }
}
