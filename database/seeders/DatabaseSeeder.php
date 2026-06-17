<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@drs.local'],
            [
                'name'     => 'Admin DRS',
                'password' => Hash::make('password'),
            ]
        );
    }
}
