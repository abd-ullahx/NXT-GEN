<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\OutlookController;
use App\Http\Controllers\Api\QuotationController;

// ── Public Auth & OAuth Callback Routes ──────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Microsoft OAuth2 Callback (Public redirect target from Azure AD)
Route::get('/outlook/callback', [OutlookController::class, 'callback']);
Route::get('/app/outlook/callback', [OutlookController::class, 'callback']);

// Public Customer Survey Booking Endpoint
Route::post('/survey/request', [\App\Http\Controllers\Api\LeadController::class, 'submitSurveyRequest']);
Route::get('/survey/approve-reschedule', [LeadController::class, 'clientApproveReschedule']);

// Public Job Schedule Confirm & Reschedule routes
Route::get('/job/schedule/confirm', [\App\Http\Controllers\JobScheduleController::class, 'confirm']);
Route::get('/job/schedule/reschedule', [\App\Http\Controllers\JobScheduleController::class, 'showRescheduleForm']);
Route::post('/job/schedule/reschedule', [\App\Http\Controllers\JobScheduleController::class, 'submitReschedule']);

// Public Customer Quote Request / Lead Intake (from website)
Route::post('/leads', [LeadController::class, 'store']);

// Public quotation approve link target (clicked from the emailed Approve button).
Route::get('/quotation/approve', [QuotationController::class, 'publicApprove']);
Route::post('/invoice/{id}/simulate-pay', [\App\Http\Controllers\Api\InvoiceController::class, 'simulatePay']);

// Public 3-Method Payment Processing Routes (Stripe, Bank, Cash)
Route::get('/payment/checkout', [\App\Http\Controllers\Api\PaymentController::class, 'checkout']);
Route::post('/payment/stripe/process', [\App\Http\Controllers\Api\PaymentController::class, 'processStripe']);
Route::post('/payment/bank/process', [\App\Http\Controllers\Api\PaymentController::class, 'processBank']);
Route::post('/payment/cash/process', [\App\Http\Controllers\Api\PaymentController::class, 'processCash']);

// Stripe Webhook (No Auth Required)
Route::post('/webhooks/stripe', [\App\Http\Controllers\Api\PaymentController::class, 'handleStripeWebhook']);

// Public email tracking & click routes.
Route::get('/outlook/track', [OutlookController::class, 'track']);
Route::get('/outlook/click', [OutlookController::class, 'trackClick']);

// Public Mobile App Customer Video Call Endpoints
Route::get('/customer/video-call/verify', [LeadController::class, 'verifyCustomerCall']);
Route::post('/customer/video-call/respond', [LeadController::class, 'customerRespondCall']);

// Public Mobile App — Start & Poll Lead Video Calls (no Sanctum token on Flutter app)
Route::post('/leads/{id}/video-call/start', [LeadController::class, 'startVideoCall']);
Route::get('/leads/{id}/video-call/status', [LeadController::class, 'getCallStatus']);
Route::post('/leads/{id}/video-call/status', [LeadController::class, 'syncCallStatus']);

// ── Call Recording & Speech-to-Text Transcription Routes ──────────────
// Twilio Telephony Webhook (RecordingStatusCallback)
Route::post('/webhooks/twilio/recording', [\App\Http\Controllers\Api\TwilioWebhookController::class, 'handleRecordingCallback']);

// Call Recording Audio Streaming (Signed / Authorized Route)
Route::get('/calls/{call}/audio', [\App\Http\Controllers\Api\CallController::class, 'streamRecording'])->name('api.calls.audio');

// Call Recording Upload (from WebRTC browser call or manual upload)
Route::post('/calls/{call}/recording', [\App\Http\Controllers\Api\CallController::class, 'uploadRecording'])->name('api.calls.recording');

// Call Management & Transcription Endpoints
Route::get('/calls', [\App\Http\Controllers\Api\CallController::class, 'index']);
Route::post('/calls', [\App\Http\Controllers\Api\CallController::class, 'store']);
Route::get('/calls/{call}', [\App\Http\Controllers\Api\CallController::class, 'show']);
Route::post('/calls/{call}/retry', [\App\Http\Controllers\Api\CallController::class, 'retryTranscription']);


// Public Mobile App Surveyor Duties (Flutter app fetches duties, client details, and calendar events)
Route::get('/surveyor/duties', [LeadController::class, 'surveyorDuties']);
Route::post('/surveyor/duties/{id}/start', [LeadController::class, 'startSurveyDuty']);
Route::get('/leads/{id}/pdf', [LeadController::class, 'downloadPdf']);

// Public Mobile App Survey Media Upload (Flutter app posts images/videos/notes from survey)
Route::post('/survey/upload-media', [\App\Http\Controllers\Api\SurveyMediaController::class, 'upload']);
Route::get('/survey/media', [\App\Http\Controllers\Api\SurveyMediaController::class, 'index']);
Route::delete('/survey/media/{id}', [\App\Http\Controllers\Api\SurveyMediaController::class, 'destroy']);

// Public Flutter Surveyor App — Report Submission & Status Updates
// These are called from the Flutter surveyor app which does NOT use Sanctum tokens.
// Public Flutter Surveyor / Mobile App Live Chat & Team Communication
Route::get('/chat/channels', [\App\Http\Controllers\Api\ChatController::class, 'channels']);
Route::post('/chat/channels', [\App\Http\Controllers\Api\ChatController::class, 'createChannel']);
Route::delete('/chat/channels/{id}', [\App\Http\Controllers\Api\ChatController::class, 'deleteChannel']);
Route::get('/chat/members', [\App\Http\Controllers\Api\ChatController::class, 'members']);
Route::get('/chat/messages', [\App\Http\Controllers\Api\ChatController::class, 'messages']);
Route::post('/chat/messages', [\App\Http\Controllers\Api\ChatController::class, 'sendMessage']);
Route::put('/chat/messages/{id}', [\App\Http\Controllers\Api\ChatController::class, 'updateMessage']);

// Public Direct-message Audio/Video Calls (Flutter Surveyor App & Web Panel)
Route::post('/chat/calls/start', [\App\Http\Controllers\Api\ChatCallController::class, 'start']);
Route::get('/chat/calls/incoming', [\App\Http\Controllers\Api\ChatCallController::class, 'incoming']);
Route::get('/chat/calls/{id}/status', [\App\Http\Controllers\Api\ChatCallController::class, 'status']);
Route::post('/chat/calls/{id}/action', [\App\Http\Controllers\Api\ChatCallController::class, 'action']);

Route::get('/jobs', [\App\Http\Controllers\Api\JobController::class, 'index']);
Route::post('/jobs/{id}/start-ride', [\App\Http\Controllers\Api\JobController::class, 'startRide']);
Route::post('/jobs/{id}/complete', [\App\Http\Controllers\Api\JobController::class, 'complete']);
Route::get('/drivers', [LeadController::class, 'drivers']);
Route::post('/leads/{id}/assign-driver', [LeadController::class, 'assignDriver']);
Route::get('/jobs/reschedule-requests', [\App\Http\Controllers\Api\JobController::class, 'rescheduleRequests']);
Route::post('/jobs/reschedule-requests/{id}/approve', [\App\Http\Controllers\Api\JobController::class, 'approveReschedule']);
Route::post('/jobs/reschedule-requests/{id}/reject', [\App\Http\Controllers\Api\JobController::class, 'rejectReschedule']);
Route::post('/driver/location', [\App\Http\Controllers\Api\JobController::class, 'updateLocation']);

// ── Protected Routes (require Sanctum token) ─────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // FCM Token Update
    Route::post('/user/fcm-token', [AuthController::class, 'updateFcmToken']);

    // Admin Users (CRUD)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/team', [AuthController::class, 'team']);
    Route::post('/users', [AuthController::class, 'storeUser']);
    Route::put('/users/{id}', [AuthController::class, 'updateUser']);
    Route::delete('/users/{id}', [AuthController::class, 'destroyUser']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Leads
    Route::get('/leads', [LeadController::class, 'index']);
    Route::get('/leads/trash', [LeadController::class, 'trash']);
    Route::post('/leads', [LeadController::class, 'store']);
    Route::put('/leads/{id}', [LeadController::class, 'update']);
    Route::patch('/leads/{id}/status', [LeadController::class, 'updateStatus']);
    Route::put('/leads/{id}/stage', [LeadController::class, 'updateStage']);
    Route::post('/leads/{id}/approve', [LeadController::class, 'approve']);
    Route::post('/leads/{id}/approve-survey', [LeadController::class, 'approveSurvey']);
    Route::post('/leads/{id}/propose-reschedule', [LeadController::class, 'proposeReschedule']);
    Route::post('/leads/{id}/assign-surveyor', [LeadController::class, 'assignSurveyor']);
    Route::post('/leads/{id}/upload-media', [LeadController::class, 'uploadMedia']);
    Route::post('/leads/{id}/submit-survey-report', [LeadController::class, 'submitSurveyReport']);
    Route::post('/leads/{id}/restore', [LeadController::class, 'restore']);
    Route::delete('/leads/{id}/force', [LeadController::class, 'forceDelete']);
    Route::delete('/leads/{id}', [LeadController::class, 'destroy']);
    // Video Call Survey Endpoints (start/status are public — see public routes above)
    Route::post('/leads/{id}/video-call/action', [LeadController::class, 'videoCallAction']);
    Route::post('/leads/{id}/video-call/notes', [LeadController::class, 'saveVideoCallNotes']);
    Route::post('/leads/{id}/send-app-credentials', [LeadController::class, 'sendAppCredentials']);
    // Survey day reminder management
    Route::get('/leads/{id}/survey-reminders', [LeadController::class, 'surveyReminders']);
    Route::post('/leads/{id}/survey-reminders/stop', [LeadController::class, 'stopSurveyReminders']);
    Route::post('/leads/{id}/survey-reminders/{reminderId}/skip', [LeadController::class, 'skipSurveyReminder']);
    Route::put('/leads/{id}/survey-reminders/{reminderId}', [LeadController::class, 'updateSurveyReminder']);

    // Quotation reminder management
    Route::get('/leads/{id}/quotation-reminders', [LeadController::class, 'quotationReminders']);
    Route::post('/leads/{id}/quotation-reminders/stop', [LeadController::class, 'stopQuotationReminders']);
    Route::post('/leads/{id}/quotation-reminders/{reminderId}/skip', [LeadController::class, 'skipQuotationReminder']);
    Route::put('/leads/{id}/quotation-reminders/{reminderId}', [LeadController::class, 'updateQuotationReminder']);

    // Email Templates Library
    Route::get('/email-templates', [\App\Http\Controllers\Api\EmailTemplateController::class, 'index']);
    Route::post('/leads/{id}/preview-template', [\App\Http\Controllers\Api\EmailTemplateController::class, 'preview']);
    Route::post('/leads/{id}/send-template-email', [\App\Http\Controllers\Api\EmailTemplateController::class, 'send']);

    // Quotations
    Route::get('/quotations', [QuotationController::class, 'index']);
    Route::post('/quotations', [QuotationController::class, 'store']);
    Route::get('/quotations/{id}', [QuotationController::class, 'show']);
    Route::put('/quotations/{id}', [QuotationController::class, 'update']);
    Route::post('/quotations/{id}/send', [QuotationController::class, 'send']);
    Route::post('/quotations/{id}/convert-to-invoice', [QuotationController::class, 'convertToInvoice']);
    Route::delete('/quotations/{id}', [QuotationController::class, 'destroy']);

    // Jobs
    Route::get('/jobs', [\App\Http\Controllers\Api\JobController::class, 'index']);
    Route::post('/jobs/{id}/assign-driver', [\App\Http\Controllers\Api\JobController::class, 'assignDriver']);
    Route::post('/jobs/{id}/status', [\App\Http\Controllers\Api\JobController::class, 'updateStatus']);
    Route::post('/jobs/{id}/start-ride', [\App\Http\Controllers\Api\JobController::class, 'startRide']);
    Route::post('/jobs/{id}/complete', [\App\Http\Controllers\Api\JobController::class, 'complete']);

    // Contacts
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts', [ContactController::class, 'store']);

    Route::get('/invoices', [App\Http\Controllers\Api\InvoiceController::class, 'index']);
    Route::post('/invoices', [App\Http\Controllers\Api\InvoiceController::class, 'store']);
    Route::get('/invoices/{id}', [App\Http\Controllers\Api\InvoiceController::class, 'show']);
    Route::put('/invoices/{id}', [App\Http\Controllers\Api\InvoiceController::class, 'update']);
    Route::post('/invoices/{id}/send', [App\Http\Controllers\Api\InvoiceController::class, 'send']);
    Route::post('/invoices/{id}/record-payment', [App\Http\Controllers\Api\InvoiceController::class, 'recordPayment']);
    Route::post('/admin/invoices/{id}/verify-bank-transfer', [App\Http\Controllers\Api\InvoiceController::class, 'verifyBankTransfer']);
    
    // Stripe
    Route::post('/invoices/{id}/create-stripe-checkout', [App\Http\Controllers\Api\PaymentController::class, 'createStripeCheckout']);

    // Calendar
    Route::get('/calendar', [CalendarController::class, 'index']);
    Route::post('/calendar', [CalendarController::class, 'store']);
    Route::put('/calendar/{id}', [CalendarController::class, 'update']);

    // Finance
    Route::get('/finance', [FinanceController::class, 'index']);
    Route::post('/finance/transactions', [FinanceController::class, 'storeTransaction']);
    Route::put('/finance/transactions/{id}', [FinanceController::class, 'updateTransaction']);
    Route::delete('/finance/transactions/{id}', [FinanceController::class, 'destroyTransaction']);

    // Integrations
    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::put('/integrations/{id}/toggle', [IntegrationController::class, 'toggle']);

    // Settings
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::put('/settings/password', [SettingsController::class, 'updatePassword']);

    // Outlook Integration
    Route::get('/outlook/auth-url', [OutlookController::class, 'authUrl']);
    Route::get('/outlook/status', [OutlookController::class, 'status']);
    Route::post('/outlook/disconnect', [OutlookController::class, 'disconnect']);
    Route::get('/outlook/emails', [OutlookController::class, 'emails']);
    Route::post('/outlook/sync', [OutlookController::class, 'sync']);
    Route::post('/outlook/send', [OutlookController::class, 'send']);

    // Internal Slack-Style Team Chat
    Route::get('/chat/channels', [\App\Http\Controllers\Api\ChatController::class, 'channels']);
    Route::post('/chat/channels', [\App\Http\Controllers\Api\ChatController::class, 'createChannel']);
    Route::delete('/chat/channels/{id}', [\App\Http\Controllers\Api\ChatController::class, 'deleteChannel']);
    Route::get('/chat/members', [\App\Http\Controllers\Api\ChatController::class, 'members']);
    Route::get('/chat/messages', [\App\Http\Controllers\Api\ChatController::class, 'messages']);
    Route::post('/chat/messages', [\App\Http\Controllers\Api\ChatController::class, 'sendMessage']);
    Route::put('/chat/messages/{id}', [\App\Http\Controllers\Api\ChatController::class, 'updateMessage']);
    Route::delete('/chat/messages/{id}', [\App\Http\Controllers\Api\ChatController::class, 'deleteMessage']);

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\AppNotificationController::class, 'index']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\AppNotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\AppNotificationController::class, 'markAsRead']);

    // Video Calls & Video Upload Features
    Route::get('/videos', [\App\Http\Controllers\Api\VideoUploadController::class, 'index']);
    Route::post('/videos/upload', [\App\Http\Controllers\Api\VideoUploadController::class, 'upload']);
    Route::delete('/videos/{id}', [\App\Http\Controllers\Api\VideoUploadController::class, 'destroy']);
    Route::post('/video-calls/create-room', [\App\Http\Controllers\Api\VideoCallController::class, 'createRoom']);
});
