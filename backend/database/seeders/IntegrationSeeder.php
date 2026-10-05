<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Integration;

class IntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'id'        => 'video-call',
                'name'      => 'Next Gen Live Video Calls',
                'desc_text' => 'Launch HD virtual survey video calls with customers & team members featuring screen share & invite links.',
                'category'  => 'Virtual Surveys',
                'connected' => true,
                'color'     => '#c9a84c',
            ],
            [
                'id'        => 'video-upload',
                'name'      => 'Video Survey Asset Storage',
                'desc_text' => 'Drag & drop property survey video recordings (.mp4, .webm) with timeline scrubbing & lead association.',
                'category'  => 'Cloud Storage',
                'connected' => true,
                'color'     => '#3b82f6',
            ],
            [
                'id'        => 'outlook',
                'name'      => 'Microsoft Outlook & Brevo Sync',
                'desc_text' => 'Auto-capture inbound email inquiries into CRM leads & track all outbound email communications.',
                'category'  => 'Email & CRM Sync',
                'connected' => true,
                'color'     => '#0078d4',
            ],
            [
                'id'        => 'n8n',
                'name'      => 'n8n Workflow Automation',
                'desc_text' => 'Trigger automated webhook workflows, lead notifications, and multi-step pipeline actions.',
                'category'  => 'Automation',
                'connected' => true,
                'color'     => '#ff6d5a',
            ],
            [
                'id'        => 'openai',
                'name'      => 'ChatGPT & Claude Multi-AI',
                'desc_text' => 'AI-powered quotation drafting, survey summary generation & automated luxury email replies.',
                'category'  => 'Artificial Intelligence',
                'connected' => true,
                'color'     => '#10a37f',
            ],
            [
                'id'        => 'stripe',
                'name'      => 'Stripe Payments & Deposits',
                'desc_text' => 'Process online deposit payments, final invoice balances & automated customer payment receipts.',
                'category'  => 'Payments & Finance',
                'connected' => true,
                'color'     => '#635bff',
            ],
            [
                'id'        => 'google-maps',
                'name'      => 'Google Maps Distance Matrix',
                'desc_text' => 'Automatic UK postcode geocoding, mileage calculation, and transit duration estimation.',
                'category'  => 'Geolocation & Routing',
                'connected' => true,
                'color'     => '#ea4335',
            ],
        ];

        foreach ($items as $item) {
            Integration::updateOrCreate(
                ['id' => $item['id']],
                $item
            );
        }
    }
}
