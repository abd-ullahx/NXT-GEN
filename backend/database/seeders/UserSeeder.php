<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@nextgenrelocation.co.uk'],
            [
                'name'     => 'Admin User',
                'password' => Hash::make('password123'),
                'role'     => 'admin',
                'permissions' => [
                    'leads' => true, 'calendar' => true, 'contacts' => true,
                    'finance' => true, 'integrations' => true, 'outlook' => true, 'settings' => true
                ],
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@nextgenrelocation.co.uk'],
            [
                'name'     => 'Staff Member',
                'password' => Hash::make('password123'),
                'role'     => 'staff',
                'permissions' => [
                    'leads' => true, 'calendar' => true, 'contacts' => true
                ],
            ]
        );

        User::updateOrCreate(
            ['email' => 'surveyor@nextgenrelocation.co.uk'],
            [
                'name'     => 'Senior Surveyor',
                'password' => Hash::make('password123'),
                'role'     => 'surveyor',
                'permissions' => [
                    'surveyor' => true, 'calendar' => true
                ],
            ]
        );

        User::updateOrCreate(
            ['email' => 'driver@nextgenrelocation.co.uk'],
            [
                'name'     => 'Fleet Driver',
                'password' => Hash::make('password123'),
                'role'     => 'driver',
                'permissions' => [
                    'jobs' => true, 'calendar' => true
                ],
            ]
        );
    }
}
