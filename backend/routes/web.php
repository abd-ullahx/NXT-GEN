<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OutlookController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Admin\CalendarController as AdminCalendarController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Admin\IntegrationController as AdminIntegrationController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\OutlookController as AdminOutlookController;
use App\Http\Controllers\Admin\CallController as AdminCallController;
use App\Http\Controllers\CallJoinController;

/*
|--------------------------------------------------------------------------
| Web Routes (API & Admin Server Entrypoints)
|--------------------------------------------------------------------------
*/

// Public Browser Calling Join Link for Contacts
Route::get('/call/join/{token}', [CallJoinController::class, 'show'])->name('calls.join');

Route::get('/api/status', function () {
    return response()->json([
        'name' => 'Next Gen Relocation API',
        'status' => 'online',
        'version' => '1.0.0',
        'database' => 'MySQL Connected'
    ]);
});

Route::get('/admin', [DashboardController::class, 'index'])->name('admin.index');

// Admin Dashboard & Management Routes (/admin)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/leads', [AdminLeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/create', [AdminLeadController::class, 'create'])->name('leads.create');
    Route::post('/leads', [AdminLeadController::class, 'store'])->name('leads.store');
    Route::put('/leads/{id}', [AdminLeadController::class, 'update'])->name('leads.update');
    Route::delete('/leads/{id}', [AdminLeadController::class, 'destroy'])->name('leads.destroy');

    Route::get('/contacts', [AdminContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/create', [AdminContactController::class, 'create'])->name('contacts.create');
    Route::post('/contacts', [AdminContactController::class, 'store'])->name('contacts.store');

    // Calls & Transcription Routes
    Route::get('/calls', [AdminCallController::class, 'index'])->name('calls.index');
    Route::get('/calls/{call}', [AdminCallController::class, 'show'])->name('calls.show');
    Route::post('/calls', [AdminCallController::class, 'store'])->name('calls.store');
    Route::post('/calls/upload', [AdminCallController::class, 'upload'])->name('calls.upload');
    Route::post('/calls/{call}/retry', [AdminCallController::class, 'retry'])->name('calls.retry');
    
    Route::get('/calendar', [AdminCalendarController::class, 'index'])->name('calendar.index');
    Route::get('/finance', [AdminFinanceController::class, 'index'])->name('finance.index');
    Route::get('/integrations', [AdminIntegrationController::class, 'index'])->name('integrations.index');
    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
    Route::get('/outlook', [AdminOutlookController::class, 'index'])->name('outlook.index');
});

// Direct Azure OAuth callback route for http://localhost:8000/app/outlook/callback
Route::get('/app/outlook/callback', [OutlookController::class, 'callback']);

// Public quotation approval link route
Route::get('/quotation/approve', [\App\Http\Controllers\Api\QuotationController::class, 'publicApprove']);

// Public 3-Method Payment Checkout Route
Route::get('/payment/checkout', [\App\Http\Controllers\Api\PaymentController::class, 'checkout']);

// Public Customer Survey Scheduling Landing Page
Route::get('/book-survey', function (\Illuminate\Http\Request $request) {
    $leadId = $request->query('lead_id', '');
    $lead = \App\Models\Lead::find($leadId);

    $customerName = $lead ? e($lead->name) : 'Valued Customer';
    $moveType = $lead ? e($lead->move_type) : 'Relocation';
    $fromLoc = $lead ? e($lead->from_location) : 'Current Address';
    $toLoc = $lead ? e($lead->to_location) : 'Destination';

    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Schedule Your Relocation Survey | Next Gen Relocation</title>
        <style>
            * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
            body { background-color: #0b0d12; color: #f3f4f6; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .card { background: #141720; border: 1px solid rgba(201, 168, 76, 0.3); border-radius: 20px; width: 100%; max-width: 500px; padding: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); }
            .logo { color: #c9a84c; font-size: 24px; font-weight: 700; text-align: center; margin-bottom: 20px; }
            h2 { margin: 0 0 8px 0; font-size: 20px; text-align: center; }
            p { color: #9ca3af; font-size: 14px; text-align: center; margin-bottom: 24px; }
            .summary { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 14px; margin-bottom: 24px; font-size: 13px; color: #d1d5db; }
            .form-group { margin-bottom: 18px; }
            label { display: block; font-size: 12px; font-weight: 600; color: #9ca3af; margin-bottom: 6px; text-transform: uppercase; tracking: 0.05em; }
            input, select, textarea { width: 100%; background: #1c202d; border: 1px solid #2e3547; border-radius: 10px; padding: 12px; color: #fff; font-size: 14px; outline: none; transition: all 0.2s; }
            input:focus, select:focus, textarea:focus { border-color: #c9a84c; box-shadow: 0 0 0 2px rgba(201,168,76,0.2); }
            input[type='time']::-webkit-calendar-picker-indicator,
            input[type='date']::-webkit-calendar-picker-indicator {
                filter: invert(72%) sepia(18%) saturate(1008%) hue-rotate(5deg) brightness(92%) contrast(88%);
                cursor: pointer;
            }
            button { width: 100%; background: linear-gradient(135deg, #d4af37, #aa820a); border: none; border-radius: 12px; padding: 14px; color: #000; font-weight: 700; font-size: 15px; cursor: pointer; transition: transform 0.1s, opacity 0.2s; }
            button:hover { opacity: 0.95; transform: translateY(-1px); }
            .success-msg { display: none; background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; padding: 16px; border-radius: 12px; text-align: center; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='card'>
            <div class='logo'>✨ Next Gen Relocation</div>
            <h2>Schedule Relocation Survey</h2>
            <p>Hello <strong>{$customerName}</strong>! Select your preferred date and exact time for our surveyor visit or virtual tour.</p>

            <div class='summary'>
                <strong>Move Details:</strong> {$moveType}<br/>
                <strong>Route:</strong> {$fromLoc} ➔ {$toLoc}
            </div>

            <form id='surveyForm'>
                <input type='hidden' name='lead_id' value='{$leadId}' />
                
                <div class='form-group'>
                    <label>Preferred Survey Date</label>
                    <input type='date' name='preferred_date' required min='" . date('Y-m-d') . "' />
                </div>

                <div class='form-group'>
                    <label>Preferred Survey Time</label>
                    <input type='time' name='time_range' required value='10:00' />
                </div>

                <div class='form-group'>
                    <label>Survey Type</label>
                    <select name='survey_type' required>
                        <option value=''>-- Choose Survey Type --</option>
                        <option value='physical'>1. Physical — surveyor visits your property</option>
                        <option value='virtual'>2. Virtual — live video call walkthrough</option>
                        <option value='video'>3. Video upload — you send us a walkthrough video</option>
                    </select>
                </div>

                <div class='form-group'>
                    <label>Additional Notes / Special Instructions</label>
                    <textarea name='notes' rows='3' placeholder='e.g. Parking instructions, preferred contact time, etc.'></textarea>
                </div>

                <button type='submit' id='submitBtn'>Submit Survey Request</button>
            </form>

            <div id='successBox' class='success-msg'>
                🎉 <strong>Survey Request Received!</strong><br/><br/>
                Thank you! Our relocation team has received your requested date & exact time. We will confirm your survey schedule shortly.
            </div>
        </div>

        <script>
            document.getElementById('surveyForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = document.getElementById('submitBtn');
                btn.innerText = 'Submitting...';
                btn.disabled = true;

                const formData = new FormData(this);
                const payload = Object.fromEntries(formData.entries());

                try {
                    const res = await fetch('/api/survey/request', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();

                    if (res.ok) {
                        document.getElementById('surveyForm').style.display = 'none';
                        document.getElementById('successBox').style.display = 'block';
                    } else {
                        alert(data.error || 'Failed to submit request');
                        btn.innerText = 'Submit Survey Request';
                        btn.disabled = false;
                    }
                } catch (err) {
                    alert('Error connecting to server. Please try again.');
                    btn.innerText = 'Submit Survey Request';
                    btn.disabled = false;
                }
            });
        </script>
    </body>
    </html>
    ";
});

// Job Schedule Confirm & Reschedule Interactive Web Routes
Route::get('/job/schedule/confirm', [\App\Http\Controllers\JobScheduleController::class, 'confirm']);
Route::get('/job/schedule/reschedule', [\App\Http\Controllers\JobScheduleController::class, 'showRescheduleForm']);
Route::post('/job/schedule/reschedule', [\App\Http\Controllers\JobScheduleController::class, 'submitReschedule']);
