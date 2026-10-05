<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChatChannel;

class ChatChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            [
                'name'         => 'general',
                'display_name' => 'general',
                'description'  => 'Company-wide updates, announcements & general team chat.',
                'is_private'   => false,
            ],
            [
                'name'         => 'dispatch-ops',
                'display_name' => 'dispatch-ops',
                'description'  => 'Fleet moves, driver assignments, vehicle dispatches & live updates.',
                'is_private'   => false,
            ],
            [
                'name'         => 'surveys',
                'display_name' => 'surveys',
                'description'  => 'On-site & video survey reports, surveyor notes & property access.',
                'is_private'   => false,
            ],
            [
                'name'         => 'management',
                'display_name' => 'management',
                'description'  => 'Admin, managers & leadership strategy discussions.',
                'is_private'   => true,
            ],
        ];

        foreach ($channels as $c) {
            ChatChannel::updateOrCreate(
                ['name' => $c['name']],
                $c
            );
        }
    }
}
