<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::firstOrCreate(
            ['email' => 'admin@btapp.com'],
            [
                'first_name'        => 'Admin',
                'last_name'         => 'User',
                'password'          => bcrypt('admin123'),
                'account_type'      => 'superadmin',
                'status'            => 'active',
                'email_verified_at' => now(),
                'gdpr_consent'      => true,
                'gdpr_consented_at' => now(),
            ]
        );
    }
}
