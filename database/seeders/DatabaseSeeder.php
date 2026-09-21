<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $data = false;

        User::factory()->create([
            'name' => 'Daniel K',
            'email' => 'wisemindvibe@gmail.com',
            'password' => Hash::make('Daniel1465723!'),
        ]);

        if ($data) {
            $this->call([
                CompanySeeder::class,
                CatalogSeeder::class,
                DummySeeder::class,
            ]);
        }
    }
}
