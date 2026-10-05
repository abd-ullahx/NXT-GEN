<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('settings')->count() === 0) {
            DB::table('settings')->insert([
                'business_name'  => 'NEXT GEN RELOCATION LTD',
                'trading_region' => 'Slough & Home Counties',
                'contact_email'  => 'hello@nextgenrelocation.co.uk',
                'phone'          => '+44 1753 555 200',
            ]);
        }
    }
}
