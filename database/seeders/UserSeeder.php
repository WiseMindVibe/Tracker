<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Daniel K',
            'email' => 'wisemindvibe@gmail.com',
            'password' => Hash::make('Daniel1465723!'),
        ]);
    }
}
