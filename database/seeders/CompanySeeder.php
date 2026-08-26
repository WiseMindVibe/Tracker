<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::factory()->create([
            'name' => 'WiseMindVibe',
            'slug' => 'wisemindvibe',
            'proxy_host' => null,
            'proxy_port' => null,
            'proxy_username' => null,
            'proxy_password' => null,
            'status' => 'active'
            ]);
    }
}
