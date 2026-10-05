<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OutlookLeadSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Insert Outlook Inbound Emails that act as prospective leads
        DB::table('emails')->insertOrIgnore([
            [
                'message_id'      => 'msg-outlook-lead-001',
                'conversation_id' => 'conv-001',
                'direction'       => 'inbound',
                'from_email'      => 'victoria.pendleton@outlook.com',
                'from_name'       => 'Victoria Pendleton',
                'to_email'        => 'quotes@nextgenrelocation.co.uk',
                'subject'         => 'Removal Quote Request: 4-Bed Detached Move from Ascot to Kensington',
                'body_preview'    => 'Hello, I received your details via Outlook referral. We are looking to move our 4-bedroom house from Ascot to Kensington in mid-August. Please provide a quotation including packing.',
                'body_html'       => '<p>Hello, I received your details via Outlook referral. We are looking to move our 4-bedroom house from Ascot to Kensington in mid-August. Please provide a quotation including packing.</p>',
                'is_read'         => false,
                'received_at'     => '2026-08-01 08:30:00',
                'lead_id'         => 'L-1043',
                'created_at'      => '2026-08-01 08:30:00',
            ],
            [
                'message_id'      => 'msg-outlook-lead-002',
                'conversation_id' => 'conv-002',
                'direction'       => 'inbound',
                'from_email'      => 'george.harrison@outlook.com',
                'from_name'       => 'George Harrison',
                'to_email'        => 'info@nextgenrelocation.co.uk',
                'subject'         => 'Urgent: Corporate Relocation Inquiry (3 Executive Desks & IT Gear)',
                'body_preview'    => 'Hi Team, emailed via Outlook sync. We have a branch office in Slough relocating to Reading next week. Require full crate hire and weekend moving service.',
                'body_html'       => '<p>Hi Team, emailed via Outlook sync. We have a branch office in Slough relocating to Reading next week. Require full crate hire and weekend moving service.</p>',
                'is_read'         => false,
                'received_at'     => '2026-08-01 09:15:00',
                'lead_id'         => 'L-1044',
                'created_at'      => '2026-08-01 09:15:00',
            ],
            [
                'message_id'      => 'msg-outlook-lead-003',
                'conversation_id' => 'conv-003',
                'direction'       => 'inbound',
                'from_email'      => 'claire.bennett@outlook.com',
                'from_name'       => 'Claire Bennett',
                'to_email'        => 'support@nextgenrelocation.co.uk',
                'subject'         => 'Residential Move Quote — Maidenhead to Windsor (2-Bed Flat)',
                'body_preview'    => 'Good morning, looking for removal prices for a 2-bed apartment move from Maidenhead to Windsor on August 20th. Includes delicate piano handling.',
                'body_html'       => '<p>Good morning, looking for removal prices for a 2-bed apartment move from Maidenhead to Windsor on August 20th. Includes delicate piano handling.</p>',
                'is_read'         => true,
                'received_at'     => '2026-08-01 10:05:00',
                'lead_id'         => 'L-1045',
                'created_at'      => '2026-08-01 10:05:00',
            ],
        ]);

        // 2. Insert corresponding Leads linked to Outlook
        DB::table('leads')->insertOrIgnore([
            [
                'id'            => 'L-1043',
                'name'          => 'Victoria Pendleton',
                'email'         => 'victoria.pendleton@outlook.com',
                'phone'         => '+44 7700 900899',
                'source'        => 'Outlook',
                'status'        => 'new',
                'move_type'     => '4-Bed House → Kensington',
                'from_location' => 'Ascot',
                'to_location'   => 'Kensington, London',
                'move_date'     => '2026-08-18',
                'est_value'     => 5400.00,
                'ai_score'      => 9,
                'priority'      => 'Hot',
            ],
            [
                'id'            => 'L-1044',
                'name'          => 'George Harrison',
                'email'         => 'george.harrison@outlook.com',
                'phone'         => '+44 7700 900722',
                'source'        => 'Outlook',
                'status'        => 'new',
                'move_type'     => 'Corporate Branch Move',
                'from_location' => 'Slough',
                'to_location'   => 'Reading',
                'move_date'     => '2026-08-10',
                'est_value'     => 7800.00,
                'ai_score'      => 9,
                'priority'      => 'Hot',
            ],
            [
                'id'            => 'L-1045',
                'name'          => 'Claire Bennett',
                'email'         => 'claire.bennett@outlook.com',
                'phone'         => '+44 7700 900311',
                'source'        => 'Outlook',
                'status'        => 'contacted',
                'move_type'     => '2-Bed Flat + Piano Move',
                'from_location' => 'Maidenhead',
                'to_location'   => 'Windsor',
                'move_date'     => '2026-08-20',
                'est_value'     => 2950.00,
                'ai_score'      => 8,
                'priority'      => 'Warm',
            ],
        ]);
    }
}
