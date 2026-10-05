<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = 'abdullah18vk@gmail.com';
$name = 'Abdullah';

// 1. Create Lead in database
$leadId = 'L-' . rand(1000, 9999);
$lead = \App\Models\Lead::create([
    'id'                    => $leadId,
    'name'                  => $name,
    'email'                 => $email,
    'phone'                 => '+44 7700 900555',
    'source'                => 'Website Form',
    'status'                => 'new',
    'stage'                 => 'Welcome Email',
    'welcome_email_sent_at' => now(),
    'move_type'             => '3-Bed House Relocation',
    'from_location'         => 'Slough',
    'to_location'           => 'Windsor',
    'move_date'             => '2026-08-15',
    'est_value'             => 3200.00,
    'ai_score'              => 9,
    'priority'              => 'Hot',
]);

echo "Lead created successfully: " . $lead->id . "\n";

// 2. Send Welcome Email via Outlook Service
$outlookService = app(\App\Services\OutlookService::class);
$status = $outlookService->getConnectionStatus();

echo "Outlook Connection Status: " . json_encode($status) . "\n";

if ($status['connected']) {
    $subject = "Welcome to Next Gen Relocation - We Received Your Inquiry ({$lead->id})";
    $welcomeBody = "
        <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 12px; padding: 24px;'>
            <h2 style='color: #c9a84c; margin-top: 0;'>Welcome to Next Gen Relocation!</h2>
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Thank you for reaching out to us regarding your upcoming move from <strong>Slough</strong> to <strong>Windsor</strong>.</p>
            <p>We have successfully received your inquiry (Ref: <strong>{$lead->id}</strong>) and our relocation team is reviewing the details to provide you with the best tailored moving quote.</p>
            <br/>
            <div style='background-color: #f9f8f3; padding: 16px; border-radius: 8px; border-left: 4px solid #c9a84c;'>
                <p style='margin: 0 0 8px 0; font-weight: bold;'>Your Inquiry Details:</p>
                <ul style='margin: 0; padding-left: 20px;'>
                    <li><strong>Move Type:</strong> 3-Bed House Relocation</li>
                    <li><strong>Estimated Date:</strong> 15 Aug 2026</li>
                    <li><strong>Reference ID:</strong> {$lead->id}</li>
                </ul>
            </div>
            <br/>
            <p>One of our relocation specialists will get in touch with you shortly. In the meantime, feel free to reply directly to this email if you have any immediate questions.</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'/>
            <p style='font-size: 12px; color: #777; margin-bottom: 0;'>Best regards,<br/><strong>Next Gen Relocation Team</strong></p>
        </div>
        <!-- Tracking Pixel -->
        <img src=\"http://localhost:8000/api/outlook/track?lead_id={$lead->id}\" width=\"1\" height=\"1\" style=\"display:none;\" alt=\"\" />
    ";

    try {
        $result = $outlookService->sendEmail($email, $subject, $welcomeBody);
        echo "RESULT: " . json_encode($result) . "\n";
    } catch (\Exception $e) {
        echo "ERROR sending email: " . $e->getMessage() . "\n";
    }
} else {
    echo "Outlook account is not connected yet. Connect Microsoft Outlook in Integrations page to send live emails.\n";
}
