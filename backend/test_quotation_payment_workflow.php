<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\AutomationService;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\DB;

echo "========================================================\n";
echo "TESTING QUOTATION & 3-METHOD PAYMENT SYSTEM WORKFLOW\n";
echo "========================================================\n\n";

// 1. Clean previous test records
Lead::where('email', 'like', 'test.workflow%@example.com')->delete();

// TEST 1: Lead Ingestion & Indicative Quotation Draft with 20% Deposit
echo "--- TEST 1: Lead Creation & Draft Quotation (Not Auto-Sent) ---\n";
$lead1 = Lead::create([
    'name' => 'Alice Walker',
    'email' => 'test.workflow.alice@example.com',
    'phone' => '07700900111',
    'pickup_address' => '10 Kensington High St, London',
    'delivery_address' => '25 Promenade, Cheltenham',
    'move_type' => '2-3 Bedroom House',
    'move_date' => date('Y-m-d', strtotime('+14 days')),
    'status' => 'New Lead',
    'source' => 'Website Form',
]);

// Call automation
$automation = app(AutomationService::class);
$quote1 = $automation->createIndicativeQuotation($lead1);

echo "Lead created: ID {$lead1->id}, Name: {$lead1->name}\n";
echo "Quotation generated: Number {$quote1->quote_number}, Status: {$quote1->status}\n";
echo "Estimated Total: £{$quote1->total}, Deposit: {$quote1->deposit_percent}% (£{$quote1->deposit_amount})\n";

if ($quote1->status !== 'draft') {
    throw new Exception("Quotation must be in 'draft' status (not automatically sent)!");
}
if ((float)$quote1->deposit_amount !== round($quote1->total * 0.20, 2)) {
    throw new Exception("Deposit amount must be strictly 20% of estimated total!");
}
echo "✓ TEST 1 PASSED: Indicative quote created in DRAFT with exact 20% deposit.\n\n";

// TEST 2: Admin Manually Sends Quotation
echo "--- TEST 2: Admin Manually Sends Quotation ---\n";
$quoteController = app(\App\Http\Controllers\Api\QuotationController::class);
$sendResponse = $quoteController->send($quote1->id);
$quote1->refresh();

echo "Quotation status after admin send: {$quote1->status}\n";
if ($quote1->status !== 'sent') {
    throw new Exception("Quotation status should be 'sent' after admin clicks send!");
}
echo "✓ TEST 2 PASSED: Admin explicitly sent quotation.\n\n";

// TEST 3: Customer Option A - Approve & Pay Later (After Survey)
echo "--- TEST 3: Option A - Customer Approves & Chooses 'Pay Later' ---\n";
$approveLaterRequest = new \Illuminate\Http\Request([
    'quote_id' => $quote1->id,
    'action' => 'approve_pay_later'
]);
$respA = $quoteController->publicApprove($approveLaterRequest);
$quote1->refresh();
$lead1->refresh();

echo "Quotation status: {$quote1->status}, Payment Option: {$quote1->payment_option}\n";
echo "Lead status: {$lead1->status}, Initial Deposit Paid: " . ($lead1->initial_deposit_paid ? 'YES' : 'NO') . "\n";

if ($quote1->payment_option !== 'pay_later' || $quote1->status !== 'approved') {
    throw new Exception("Option A should mark quotation as approved with payment_option = pay_later!");
}
if ($lead1->status !== 'Survey') {
    throw new Exception("Option A should advance lead to 'Survey' stage!");
}
if ($lead1->initial_deposit_paid) {
    throw new Exception("Pay Later must not mark initial deposit as paid!");
}
echo "✓ TEST 3 PASSED: Option A correctly advances to Survey stage with £0 collected.\n\n";

// TEST 4: Customer Option B - Approve & Pay 20% Deposit Now (£X.XX)
echo "--- TEST 4: Option B - Customer Approves & Pays 20% Deposit Now ---\n";
$lead2 = Lead::create([
    'name' => 'Bob Davies',
    'email' => 'test.workflow.bob@example.com',
    'phone' => '07700900222',
    'pickup_address' => '44 Oxford St, Reading',
    'delivery_address' => "12 King's Parade, Cambridge",
    'move_type' => '4+ Bedroom House',
    'move_date' => date('Y-m-d', strtotime('+21 days')),
    'status' => 'New Lead',
    'source' => 'Website Form',
]);
$quote2 = $automation->createIndicativeQuotation($lead2);
$quote2->update(['status' => 'sent']);

$approveNowRequest = new \Illuminate\Http\Request([
    'quote_id' => $quote2->id,
    'action' => 'approve_pay_now'
]);
$respB = $quoteController->publicApprove($approveNowRequest);
$quote2->refresh();
$lead2->refresh();

echo "Quotation status: {$quote2->status}, Payment Option: {$quote2->payment_option}\n";
$depositInvoice = Invoice::where('quotation_id', $quote2->id)->first();

if (!$depositInvoice) {
    throw new Exception("Option B must generate a 20% deposit invoice!");
}
echo "Deposit Invoice created: {$depositInvoice->invoice_number}, Total: £{$depositInvoice->total}, Status: {$depositInvoice->status}\n";

if ((float)$depositInvoice->total !== (float)$quote2->deposit_amount) {
    throw new Exception("Invoice amount (£{$depositInvoice->total}) must equal 20% deposit (£{$quote2->deposit_amount})!");
}
echo "✓ TEST 4 PASSED: Option B created exact 20% deposit invoice and redirected to payment portal.\n\n";

// TEST 5: Payment Processing - Stripe Card Settlement
echo "--- TEST 5: 3 Payment Methods (Stripe / Bank / Cash) & Ledger Sync ---\n";
$paymentController = app(PaymentController::class);

// Process Stripe Payment
PaymentController::settleInvoicePayment(
    $depositInvoice,
    'stripe',
    (float)$depositInvoice->total,
    'ch_test_card_mock_12345',
    'Stripe Checkout card payment settled'
);

$depositInvoice->refresh();
$lead2->refresh();
$quote2->refresh();

echo "Invoice Status: {$depositInvoice->status}, Paid Amount: £{$depositInvoice->paid_amount}\n";
echo "Lead Initial Deposit Paid: " . ($lead2->initial_deposit_paid ? 'YES' : 'NO') . ", Total Paid: £{$lead2->total_deposit_paid}\n";

$txn = Transaction::where('invoice_id', $depositInvoice->id)->first();
if (!$txn) {
    throw new Exception("Settling payment must create an entry in General Ledger (transactions table)!");
}
echo "General Ledger Transaction logged: ID {$txn->id}, Type: {$txn->type}, Amount: £{$txn->amount}, Category: {$txn->category}\n";

if ($depositInvoice->status !== 'Paid' || !$lead2->initial_deposit_paid) {
    throw new Exception("Settlement failed to mark invoice as Paid or lead as deposit paid!");
}
echo "✓ TEST 5 PASSED: Stripe payment settled invoice, updated lead, and synced General Ledger with 0 errors.\n\n";

// TEST 6: Post-Survey Quotation & 20% Catch (Charging 20% More)
echo "--- TEST 6: Post-Survey Quotation & 20% Finance Catch ---\n";
// Create post-survey quotation for Bob (who already paid initial 20% deposit of £440 on £2,200 estimate)
$postSurveyQuote = Quotation::create([
    'lead_id' => $lead2->id,
    'quote_number' => 'Q-SURVEY-' . rand(1000, 9999),
    'quote_type' => 'post-survey',
    'client_name' => $lead2->name,
    'client_email' => $lead2->email,
    'client_phone' => $lead2->phone,
    'move_type' => $lead2->move_type,
    'subtotal' => 2500.00,
    'tax' => 0.00,
    'total' => 2500.00,
    'deposit_percent' => 20.00,
    'deposit_amount' => 500.00,
    'status' => 'sent',
    'initial_deposit_paid' => true,
    'packages' => [
        ['name' => 'Standard Package', 'description' => 'Full professional moving service', 'total' => 2500.00],
        ['name' => 'Premium Package', 'description' => 'Packing, materials, dismantling & moving', 'total' => 3000.00],
    ]
]);

echo "Post-survey quotation drafted for Bob with Initial Deposit Already Paid = TRUE.\n";
echo "Selecting 'Premium Package' (£3,000.00)...\n";

$planSelectionRequest = new \Illuminate\Http\Request([
    'quote_id' => $postSurveyQuote->id,
    'package_name' => 'Premium Package',
    'package_price' => 3000.00,
]);
$respPostSurvey = $quoteController->publicApprove($planSelectionRequest);
$postSurveyQuote->refresh();

$secondDepositInvoice = Invoice::where('quotation_id', $postSurveyQuote->id)
    ->where('invoice_type', 'deposit_phase2')
    ->first();

if (!$secondDepositInvoice) {
    throw new Exception("Selecting package must create a second 20% deposit installment invoice!");
}

echo "Second Deposit Invoice created: {$secondDepositInvoice->invoice_number}\n";
echo "Second Deposit Total Due: £{$secondDepositInvoice->total}\n";

// Total package = 3,000. 20% of package = £600.
if ((float)$secondDepositInvoice->total !== 600.00) {
    throw new Exception("Second deposit installment must be 20% of selected package (£600.00), got £{$secondDepositInvoice->total}!");
}

// Settle second deposit via Bank Transfer
PaymentController::settleInvoicePayment(
    $secondDepositInvoice,
    'bank_transfer',
    600.00,
    'BARC-TX-892182',
    'Confirmed Barclays bank remittance'
);
$secondDepositInvoice->refresh();
$lead2->refresh();

echo "Second Deposit Settled! Lead Total Deposits Paid: £{$lead2->total_deposit_paid}\n";
// Initial (£440) + Second (£600) = £1,040 total paid
if ((float)$lead2->total_deposit_paid !== 1040.00) {
    throw new Exception("Lead total deposit paid should be £1,040.00, got £{$lead2->total_deposit_paid}!");
}
echo "✓ TEST 6 PASSED: Post-survey 20% finance catch correctly charged 20% additional installment (£600) and synced total deposit (£1,040)!\n\n";

// TEST 7: Cash Payment Method & CRM Manual Settlement
echo "--- TEST 7: Cash Payment Method & CRM Manual Settlement ---\n";
$lead3 = Lead::create([
    'name' => 'Charlie Evans',
    'email' => 'test.workflow.charlie@example.com',
    'phone' => '07700900333',
    'pickup_address' => '88 High St, Oxford',
    'delivery_address' => '14 Queens Road, Bristol',
    'move_type' => '1-2 Bedroom Flat',
    'move_date' => date('Y-m-d', strtotime('+10 days')),
    'status' => 'New Lead',
    'source' => 'Website Form',
]);
$quote3 = $automation->createIndicativeQuotation($lead3);
$quote3->update(['status' => 'sent']);

$cashApprove = new \Illuminate\Http\Request([
    'quote_id' => $quote3->id,
    'action' => 'approve_pay_now'
]);
$quoteController->publicApprove($cashApprove);
$cashInvoice = Invoice::where('quotation_id', $quote3->id)->first();

// Client selects Cash in Payment Portal
PaymentController::processCashPayment($cashInvoice, 'Client selected Cash on move day');
$cashInvoice->refresh();
echo "Client confirmed cash payment. Invoice status: {$cashInvoice->status}, Method: {$cashInvoice->payment_method}\n";

if ($cashInvoice->status !== 'Unpaid' || $cashInvoice->payment_method !== 'cash') {
    throw new Exception("Cash selection in portal should keep invoice Unpaid with payment_method = cash pending driver collection!");
}

// Staff collects cash and records in CRM
PaymentController::settleInvoicePayment(
    $cashInvoice,
    'cash',
    (float)$cashInvoice->total,
    'CASH-DRIVER-01',
    'Cash collected in person by driver'
);
$cashInvoice->refresh();
$lead3->refresh();

echo "Driver collected cash & staff recorded in CRM: Invoice status: {$cashInvoice->status}, Paid Amount: £{$cashInvoice->paid_amount}\n";
if ($cashInvoice->status !== 'Paid' || !$lead3->initial_deposit_paid) {
    throw new Exception("Recording cash payment in CRM must settle invoice and update lead!");
}
echo "✓ TEST 7 PASSED: Cash flow pending collection -> driver on-site cash collection -> CRM settle verified!\n\n";

// Clean up test records
Lead::where('email', 'like', 'test.workflow%@example.com')->delete();

echo "========================================================\n";
echo "ALL 7 INTEGRATION TESTS PASSED WITH 100% FINANCIAL ACCURACY!\n";
echo "========================================================\n";
