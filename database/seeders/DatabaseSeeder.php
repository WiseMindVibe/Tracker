<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        User::factory()->create([
            'name' => 'Daniel K',
            'email' => 'wisemindvibe@gmail.com',
            'password' => Hash::make('Daniel1465723!'),
            ]);

        $this->call([
            CompanySeeder::class,
            CatalogSeeder::class,
            DummySeeder::class,
        ]);
    }
}
