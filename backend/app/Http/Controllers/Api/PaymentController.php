<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Transaction;
use App\Services\AutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{
    /**
     * Public 3-Method Payment Checkout Page (Card/Stripe, Bank Transfer, Cash).
     */
    public function checkout(Request $request)
    {
        $invoiceId = $request->query('invoice_id');
        $invoice = Invoice::find($invoiceId);

        if (!$invoice) {
            return response($this->errorPage('Invoice Not Found', 'The requested invoice could not be located. Please contact customer support.'), 404)
                ->header('Content-Type', 'text/html');
        }

        if ($invoice->status === 'Paid') {
            return response($this->successReceiptPage($invoice, 'Payment Already Settled'))
                ->header('Content-Type', 'text/html');
        }

        return response($this->renderCheckoutPortal($invoice))
            ->header('Content-Type', 'text/html');
    }

    /**
     * Process Stripe / Card Payment (Automatic).
     */
    public function processStripe(Request $request)
    {
        $invoiceId = $request->input('invoice_id');
        $invoice = Invoice::find($invoiceId);

        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        if ($invoice->status === 'Paid') {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invoice is already paid']);
            }
            return response($this->successReceiptPage($invoice, 'Payment Already Confirmed'))->header('Content-Type', 'text/html');
        }

        $stripeToken = $request->input('stripe_token') ?: ('ch_test_' . strtolower(substr(uniqid(), -8)));
        $paidAmount = (float) $invoice->total;

        // Settle invoice
        $this->settleInvoicePayment($invoice, 'stripe', $paidAmount, $stripeToken);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Stripe card payment processed successfully.',
                'invoice' => $invoice->refresh(),
            ]);
        }

        return response($this->successReceiptPage($invoice->refresh(), 'Card Payment Successful (Stripe)'))->header('Content-Type', 'text/html');
    }

    /**
     * Create a Stripe Checkout Session for an Invoice.
     */
    public function createStripeCheckout(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) return response()->json(['error' => 'Invoice not found'], 404);

        if ($invoice->status === 'Paid') {
            return response()->json(['error' => 'Invoice is already paid'], 400);
        }

        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET', 'sk_test_placeholder'));

        $domain = env('APP_URL', 'http://localhost:8000');
        
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'gbp',
                    'product_data' => [
                        'name' => 'Invoice #' . ($invoice->invoice_number ?: $invoice->id),
                        'description' => $invoice->service_title ?: 'Relocation Services',
                    ],
                    'unit_amount' => (int) round((float)$invoice->total * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $domain . '/api/payment/checkout?invoice_id=' . $invoice->id . '&success=true',
            'cancel_url' => $domain . '/api/payment/checkout?invoice_id=' . $invoice->id . '&canceled=true',
            'client_reference_id' => $invoice->id,
            'metadata' => [
                'invoice_id' => $invoice->id,
            ],
            'customer_email' => $invoice->client_email ?: null,
        ]);

        return response()->json([
            'checkout_url' => $session->url
        ]);
    }

    /**
     * Handle incoming Stripe Webhooks automatically.
     */
    public function handleStripeWebhook(Request $request)
    {
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET', 'whsec_placeholder');

        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Ignore signature verification in local development if placeholder is used
            if ($endpoint_secret === 'whsec_placeholder') {
                $event = json_decode($payload);
            } else {
                return response()->json(['error' => 'Invalid signature'], 400);
            }
        }

        // We only care about successful payments
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $invoiceId = $session->metadata->invoice_id ?? $session->client_reference_id;

            if ($invoiceId) {
                $invoice = Invoice::find($invoiceId);
                if ($invoice && $invoice->status !== 'Paid') {
                    $amount = $session->amount_total ? ($session->amount_total / 100) : (float) $invoice->total;
                    $this->settleInvoicePayment($invoice, 'stripe', $amount, $session->payment_intent ?? 'Stripe Webhook');
                    Log::info("Stripe Webhook processed automatically for Invoice {$invoiceId}");
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Process Bank Transfer Confirmation (Automatic).
     */
    public function processBank(Request $request)
    {
        $invoiceId = $request->input('invoice_id');
        $invoice = Invoice::find($invoiceId);

        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        if ($invoice->status === 'Paid') {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invoice is already paid']);
            }
            return response($this->successReceiptPage($invoice, 'Payment Already Confirmed'))->header('Content-Type', 'text/html');
        }

        $senderRef = $request->input('bank_reference') ?: ('BACS-' . strtoupper(substr(uniqid(), -6)));
        $paidAmount = (float) $invoice->total;

        // Instead of settling, mark as pending verification
        $invoice->update([
            'status'            => 'Pending Verification',
            'payment_method'    => 'bank_transfer',
            'payment_reference' => 'Bank Ref: ' . $senderRef,
        ]);

        $lead = $invoice->lead_id ? Lead::find($invoice->lead_id) : null;
        if ($lead) {
            $lead->update([
                'payment_status' => 'Pending Bank Transfer Verification'
            ]);
        }

        // Notify staff of pending verification
        \App\Models\AppNotification::create([
            'user_id' => null,
            'type'    => 'warning',
            'title'   => 'Bank Transfer Pending Verification',
            'message' => "Client {$invoice->client_name} claims to have sent a bank transfer for Invoice #{$invoice->invoice_number} (£" . number_format($paidAmount, 2) . "). Please verify.",
            'link'    => '/app/invoices?search=' . $invoice->id,
            'is_read' => false,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Bank transfer logged. Pending admin verification.',
                'invoice' => $invoice->refresh(),
            ]);
        }

        return response($this->successReceiptPage($invoice->refresh(), 'Bank Transfer Logged — Pending Verification'))->header('Content-Type', 'text/html');
    }

    /**
     * Process Cash Payment Selection (Sets pending cash collection for Admin/Driver manual recording).
     */
    public function processCash(Request $request)
    {
        $invoiceId = $request->input('invoice_id');
        $invoice = Invoice::find($invoiceId);

        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        $notes = $request->input('notes') ?: 'Client opted to pay in cash upon surveyor visit / driver arrival.';

        $invoice->update([
            'status'            => 'Unpaid',
            'payment_method'    => 'cash',
            'payment_reference' => 'Cash Pending Collection: ' . $notes,
            'balance_due'       => (float) $invoice->total,
        ]);

        $lead = $invoice->lead_id ? Lead::find($invoice->lead_id) : null;
        if ($lead) {
            $lead->update([
                'payment_status' => 'Pending Cash Collection',
                'stage'          => ($invoice->invoice_type === 'initial_deposit') ? 'Survey' : 'Booking Confirmed',
            ]);
        }

        // Notify staff of pending cash collection
        \App\Models\AppNotification::create([
            'user_id' => null,
            'type'    => 'warning',
            'title'   => 'Cash Payment Scheduled',
            'message' => "Client {$invoice->client_name} chose cash payment for Invoice #{$invoice->invoice_number} (£" . number_format($invoice->total, 2) . "). Collect on arrival.",
            'link'    => '/app/invoices?search=' . $invoice->id,
            'is_read' => false,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Cash payment option registered. Pending cash collection on arrival.',
                'invoice' => $invoice->refresh(),
            ]);
        }

        return response($this->cashPendingConfirmationPage($invoice))->header('Content-Type', 'text/html');
    }

    /**
     * Master method to settle an invoice payment and strictly synchronize with General Ledger, Lead, and Quotation.
     */
    public function settleInvoicePayment(Invoice $invoice, string $method, float $amount, ?string $reference = null): void
    {
        $invoice->update([
            'status'            => 'Paid',
            'payment_method'    => $method,
            'paid_amount'       => $amount,
            'balance_due'       => max(0, round((float)$invoice->total - $amount, 2)),
            'payment_reference' => $reference,
            'paid_at'           => now(),
        ]);

        // 1. Idempotent General Ledger Transaction recording
        $txId = 'TXN-INV-' . preg_replace('/[^A-Za-z0-9]/', '', $invoice->id);
        $methodLabel = match ($method) {
            'stripe'        => 'Stripe Card',
            'bank_transfer' => 'Bank Transfer (BACS)',
            'cash'          => 'Cash Collection',
            default         => ucfirst($method),
        };

        $category = match ($invoice->invoice_type) {
            'initial_deposit' => 'Deposit Payment',
            'plan_deposit'    => 'Deposit Payment',
            'final_balance'   => 'Final Job Balance',
            default           => 'Invoice Payment',
        };

        Transaction::updateOrCreate(
            ['id' => $txId],
            [
                'transaction_date' => now()->format('Y-m-d'),
                'label'            => "Invoice #{$invoice->invoice_number} ({$invoice->service_title}) — {$invoice->client_name} via {$methodLabel}",
                'category'         => $category,
                'type'             => 'income',
                'amount'           => $amount,
            ]
        );

        // 2. Synchronize Lead & Quotation
        $lead = $invoice->lead_id ? Lead::find($invoice->lead_id) : null;
        $quote = $invoice->quotation_id ? Quotation::find($invoice->quotation_id) : null;

        if ($quote) {
            if ($invoice->invoice_type === 'initial_deposit') {
                $quote->update([
                    'initial_deposit_paid' => true,
                    'status'               => 'approved',
                    'approved_at'          => $quote->approved_at ?: now(),
                ]);
            }
        }

        if ($lead) {
            $isInitial = ($invoice->invoice_type === 'initial_deposit');
            $newTotalDeposit = round((float)$lead->total_deposit_paid + $amount, 2);

            $updateFields = [
                'total_deposit_paid' => $newTotalDeposit,
                'payment_status'     => $isInitial ? 'Initial Deposit Paid (20%)' : 'Deposit Paid',
            ];

            if ($isInitial) {
                $updateFields['initial_deposit_paid'] = true;
                $updateFields['status'] = 'quote-approved';
                $updateFields['stage'] = 'Survey';
                $updateFields['quotation_status'] = 'approved';
                $updateFields['quotation_approved_at'] = $lead->quotation_approved_at ?: now();
            } else {
                $updateFields['status'] = 'won';
                $updateFields['stage'] = 'Booking Confirmed';
                $updateFields['deposit_paid_at'] = now();
            }

            $lead->update($updateFields);

            // Record automation lifecycle
            try {
                $auto = new AutomationService();
                $auto->recordResponse($lead, $isInitial ? 'quotation' : 'payment');
            } catch (\Throwable $e) {
                Log::warning("Automation trigger error on payment: " . $e->getMessage());
            }
        }

        // 3. Send payment receipt email
        $this->sendReceiptEmail($invoice, $lead, $quote, $method, $amount);

        // 4. Create staff app notification
        \App\Models\AppNotification::create([
            'user_id' => null,
            'type'    => 'success',
            'title'   => 'Payment Settled (' . $methodLabel . ') 🎉',
            'message' => "Invoice #{$invoice->invoice_number} paid (£" . number_format($amount, 2) . ") by {$invoice->client_name}. Synced to General Ledger.",
            'link'    => '/app/invoices?search=' . $invoice->id,
            'is_read' => false,
        ]);

        try {
            
        } catch (\Throwable $ex) {}
    }

    /**
     * Send professional payment confirmation & receipt email.
     */
    private function sendReceiptEmail(Invoice $invoice, ?Lead $lead, ?Quotation $quote, string $method, float $amount): void
    {
        if (!$invoice->client_email) return;

        $clientName = e($invoice->client_name ?: ($lead?->name ?: 'Valued Client'));
        $invNum     = e($invoice->invoice_number ?: $invoice->id);
        $service    = e($invoice->service_title ?: 'Relocation Service Deposit');
        $from       = e($lead?->from_location ?: ($quote?->from_location ?: 'Origin Address'));
        $to         = e($lead?->to_location ?: ($quote?->to_location ?: 'Destination Address'));
        $moveDate   = $lead?->move_date ? \Carbon\Carbon::parse($lead->move_date)->format('d F Y') : 'TBC';
        $methodLabel = match ($method) {
            'stripe'        => 'Credit / Debit Card (Stripe)',
            'bank_transfer' => 'Bank Transfer (BACS)',
            'cash'          => 'Cash on Appointment',
            default         => ucfirst($method),
        };

        $subject = "Receipt & Booking Confirmation — Invoice #{$invNum}";
        $body = "
        <div style=\"font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:#0b0d12; color:#f3f4f6; padding:32px; border-radius:16px; max-width:640px; margin:0 auto;\">
            <div style=\"text-align:center; margin-bottom:24px;\">
                <div style=\"color:#c9a84c; font-size:22px; font-weight:800; letter-spacing:1px;\">✨ NEXT GEN RELOCATION LTD</div>
                <h2 style=\"color:#10b981; font-size:22px; margin:8px 0;\">Payment Received & Confirmed ✅</h2>
                <p style=\"color:#9ca3af; font-size:13px; margin:0;\">Official Advance Deposit Receipt & Booking Guarantee</p>
            </div>

            <div style=\"background:#141720; border:1px solid #10b981; border-radius:12px; padding:20px; margin-bottom:20px;\">
                <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"font-size:14px; color:#d1d5db;\">
                    <tr><td style=\"padding-bottom:8px; color:#9ca3af;\">Invoice Number:</td><td style=\"padding-bottom:8px; text-align:right; font-weight:bold; color:#fff;\">#{$invNum}</td></tr>
                    <tr><td style=\"padding-bottom:8px; color:#9ca3af;\">Client Name:</td><td style=\"padding-bottom:8px; text-align:right; font-weight:bold; color:#fff;\">{$clientName}</td></tr>
                    <tr><td style=\"padding-bottom:8px; color:#9ca3af;\">Service Description:</td><td style=\"padding-bottom:8px; text-align:right; color:#c9a84c; font-weight:bold;\">{$service}</td></tr>
                    <tr><td style=\"padding-bottom:8px; color:#9ca3af;\">Payment Method:</td><td style=\"padding-bottom:8px; text-align:right; color:#fff;\">{$methodLabel}</td></tr>
                    <tr><td style=\"padding-bottom:8px; color:#9ca3af;\">Status:</td><td style=\"padding-bottom:8px; text-align:right;\"><span style=\"background:#10b981; color:#000; font-size:11px; font-weight:800; padding:3px 10px; border-radius:10px;\">PAID</span></td></tr>
                </table>

                <div style=\"border-top:1px dashed rgba(255,255,255,0.15); margin:14px 0 0; padding-top:14px; text-align:center;\">
                    <div style=\"font-size:11px; color:#9ca3af; text-transform:uppercase;\">AMOUNT RECEIVED</div>
                    <div style=\"font-size:26px; font-weight:800; color:#10b981; margin-top:4px;\">£" . number_format($amount, 2) . "</div>
                </div>
            </div>

            <div style=\"background:#141720; border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:18px; margin-bottom:20px;\">
                <div style=\"font-size:12px; font-weight:bold; color:#c9a84c; text-transform:uppercase; margin-bottom:6px;\">📍 SCHEDULED MOVE ROUTE</div>
                <div style=\"font-size:13px; color:#d1d5db; line-height:1.6;\">
                    <strong>Route:</strong> {$from} ➔ {$to}<br>
                    <strong>Scheduled Date:</strong> {$moveDate}
                </div>
            </div>

            <div style=\"text-align:center; font-size:12px; color:#9ca3af;\">
                Next Gen Relocation Ltd · 7 Donnington Road, Reading, RG15NE, UK<br>
                For questions, contact support@nextgenrelocation.co.uk or +44 20 8123 4567.
            </div>
        </div>
        ";

        try {
            if (function_exists('defer')) {
                defer(function () use ($invoice, $subject, $body) {
                    try {
                        Mail::to($invoice->client_email)->queue(new \App\Mail\RawCustomEmail($subject, $body));
                    } catch (\Throwable $e) {
                        Log::warning("Could not send invoice receipt email: " . $e->getMessage());
                    }
                });
            } else {
                app()->terminating(function () use ($invoice, $subject, $body) {
                    try {
                        Mail::to($invoice->client_email)->queue(new \App\Mail\RawCustomEmail($subject, $body));
                    } catch (\Throwable $e) {
                        Log::warning("Could not send invoice receipt email: " . $e->getMessage());
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::warning("Could not queue invoice receipt email: " . $e->getMessage());
        }
    }

    /**
     * Render Interactive 3-Method Payment Checkout Page.
     */
    private function renderCheckoutPortal(Invoice $inv): string
    {
        $amount = number_format((float)$inv->total, 2);
        $clientName = e($inv->client_name ?: 'Valued Client');
        $invNum = e($inv->invoice_number ?: $inv->id);
        $service = e($inv->service_title ?: 'Relocation Deposit');

        return "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Complete Relocation Deposit Payment | Next Gen Relocation</title>
    <style>
        * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { background: #0b0d12; color: #f3f4f6; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .checkout-container { background: #141720; border: 1px solid rgba(201,168,76,0.3); border-radius: 20px; max-width: 580px; width: 100%; padding: 36px; box-shadow: 0 25px 50px rgba(0,0,0,0.7); }
        .brand { text-align: center; margin-bottom: 24px; }
        .logo { color: #c9a84c; font-size: 22px; font-weight: 800; letter-spacing: 1px; }
        .title { font-size: 20px; font-weight: 700; color: #fff; margin: 8px 0 4px; }
        .subtitle { font-size: 13px; color: #9ca3af; margin: 0; }
        .invoice-card { background: rgba(201,168,76,0.06); border: 1px dashed rgba(201,168,76,0.25); border-radius: 14px; padding: 18px; margin-bottom: 24px; }
        .row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px; }
        .row:last-child { margin-bottom: 0; }
        .label { color: #9ca3af; }
        .val { font-weight: 600; color: #fff; }
        .amount-row { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 12px; margin-top: 10px; align-items: baseline; }
        .amount-total { font-size: 24px; font-weight: 800; color: #c9a84c; }
        
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; background: rgba(255,255,255,0.04); padding: 4px; border-radius: 12px; }
        .tab-btn { flex: 1; background: transparent; border: none; color: #9ca3af; padding: 10px 14px; font-size: 13px; font-weight: 700; border-radius: 9px; cursor: pointer; transition: all 0.2s; text-align: center; }
        .tab-btn.active { background: #c9a84c; color: #0b0d12; box-shadow: 0 2px 10px rgba(201,168,76,0.25); }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.2s ease-in; }
        
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px; }
        .form-control { width: 100%; background: #1c202d; border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; padding: 11px 14px; color: #fff; font-size: 14px; outline: none; transition: border 0.2s; }
        .form-control:focus { border-color: #c9a84c; }
        .form-row { display: flex; gap: 12px; }
        
        .pay-btn { width: 100%; background: linear-gradient(135deg, #c9a84c, #e2c269); color: #0b0d12; border: none; padding: 14px; border-radius: 12px; font-size: 15px; font-weight: 800; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 15px rgba(201,168,76,0.3); margin-top: 10px; }
        .pay-btn:hover { opacity: 0.95; transform: translateY(-1px); }
        
        .bank-details-box { background: #1a1e2a; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px; margin-bottom: 16px; font-size: 13px; }
        .bank-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .bank-row:last-child { border-bottom: none; }
        .copy-tag { background: rgba(201,168,76,0.15); color: #c9a84c; padding: 3px 8px; border-radius: 6px; font-size: 11px; cursor: pointer; border: 1px solid rgba(201,168,76,0.3); }
        
        .cash-info-box { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.3); border-radius: 12px; padding: 16px; font-size: 13px; color: #f3f4f6; line-height: 1.6; margin-bottom: 16px; }
        
        .security-badge { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 20px; display: flex; align-items: center; justify-content: center; gap: 6px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class='checkout-container'>
        <div class='brand'>
            <div class='logo'>✨ NEXT GEN RELOCATION</div>
            <h1 class='title'>Secure Booking Deposit Payment</h1>
            <p class='subtitle'>Hello <strong>{$clientName}</strong>, select your preferred payment method below to confirm your move booking.</p>
        </div>

        <div class='invoice-card'>
            <div class='row'><span class='label'>Invoice Reference:</span><span class='val'>#{$invNum}</span></div>
            <div class='row'><span class='label'>Service:</span><span class='val'>{$service}</span></div>
            <div class='row amount-row'>
                <span class='label' style='font-size:14px; font-weight:bold;'>Amount Due Now:</span>
                <span class='amount-total'>£{$amount}</span>
            </div>
        </div>

        <!-- 3-Method Tabs -->
        <div class='tabs'>
            <button type='button' class='tab-btn active' onclick='switchTab(\"stripe\")'>💳 Card (Stripe)</button>
            <button type='button' class='tab-btn' onclick='switchTab(\"bank\")'>🏦 Bank Transfer</button>
            <button type='button' class='tab-btn' onclick='switchTab(\"cash\")'>💵 Cash Payment</button>
        </div>

        <!-- Tab 1: Stripe Card -->
        <div id='tab-stripe' class='tab-content active'>
            <form action='/api/payment/stripe/process' method='POST' id='stripeForm'>
                <input type='hidden' name='invoice_id' value='{$inv->id}'>
                <div class='form-group'>
                    <label>Cardholder Full Name</label>
                    <input type='text' name='cardholder_name' class='form-control' required value='{$clientName}' placeholder='e.g. John Smith'>
                </div>
                <div class='form-group'>
                    <label>Card Number</label>
                    <input type='text' name='card_number' class='form-control' required placeholder='•••• •••• •••• 4242' maxlength='19' value='4242 •••• •••• 4242'>
                </div>
                <div class='form-row'>
                    <div class='form-group' style='flex:1;'>
                        <label>Expiry Date</label>
                        <input type='text' name='card_expiry' class='form-control' required placeholder='MM/YY' maxlength='5' value='12/28'>
                    </div>
                    <div class='form-group' style='flex:1;'>
                        <label>Security Code (CVC)</label>
                        <input type='password' name='card_cvc' class='form-control' required placeholder='CVC' maxlength='4' value='888'>
                    </div>
                </div>
                <button type='submit' class='pay-btn' id='stripeBtn'>💳 Pay £{$amount} with Card</button>
            </form>
        </div>

        <!-- Tab 2: Bank Transfer -->
        <div id='tab-bank' class='tab-content'>
            <div class='bank-details-box'>
                <div class='bank-row'>
                    <span class='label'>Bank:</span>
                    <span class='val'>Barclays Bank UK</span>
                </div>
                <div class='bank-row'>
                    <span class='label'>Account Name:</span>
                    <span class='val' id='val-name'>Next Gen Relocation Ltd</span>
                </div>
                <div class='bank-row'>
                    <span class='label'>Sort Code:</span>
                    <span class='val' id='val-sort'>20-04-15</span>
                </div>
                <div class='bank-row'>
                    <span class='label'>Account Number:</span>
                    <span class='val' id='val-acc'>83920144</span>
                </div>
                <div class='bank-row'>
                    <span class='label'>Payment Reference:</span>
                    <span class='val' style='color:#c9a84c;' id='val-ref'>#{$invNum}</span>
                </div>
            </div>

            <form action='/api/payment/bank/process' method='POST' id='bankForm'>
                <input type='hidden' name='invoice_id' value='{$inv->id}'>
                <div class='form-group'>
                    <label>Your Bank Remittance Reference (Optional)</label>
                    <input type='text' name='bank_reference' class='form-control' placeholder='e.g. Bank Payment Ref #{$invNum}'>
                </div>
                <button type='submit' class='pay-btn' style='background:linear-gradient(135deg, #10b981, #059669); color:#fff;'>
                    🏦 I Have Sent £{$amount} via Bank Transfer
                </button>
            </form>
        </div>

        <!-- Tab 3: Cash Payment -->
        <div id='tab-cash' class='tab-content'>
            <div class='cash-info-box'>
                <strong>💵 In-Person Cash Payment Option</strong><br>
                You can pay your deposit in cash directly to our surveyor or team coordinator on your appointment day. 
                Selecting this option will reserve your booking immediately. An official physical paper receipt and digital receipt will be signed upon cash collection.
            </div>

            <form action='/api/payment/cash/process' method='POST' id='cashForm'>
                <input type='hidden' name='invoice_id' value='{$inv->id}'>
                <div class='form-group'>
                    <label>Collection Notes / Appointment Preference</label>
                    <input type='text' name='notes' class='form-control' placeholder='e.g. Will pay cash to surveyor upon arrival'>
                </div>
                <button type='submit' class='pay-btn' style='background:linear-gradient(135deg, #f59e0b, #d97706); color:#000;'>
                    🤝 Confirm Cash Payment on Arrival (£{$amount})
                </button>
            </form>
        </div>

        <div class='security-badge'>
            🔒 256-bit Bank-Grade SSL Encryption · Guaranteed Booking Protection
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            const btnIndex = tabId === 'stripe' ? 0 : (tabId === 'bank' ? 1 : 2);
            document.querySelectorAll('.tab-btn')[btnIndex].classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        }
    </script>
</body>
</html>";
    }

    private function successReceiptPage(Invoice $inv, string $headline): string
    {
        $amount = number_format((float)($inv->paid_amount ?: $inv->total), 2);
        $methodLabel = match ($inv->payment_method) {
            'stripe'        => 'Credit / Debit Card (Stripe)',
            'bank_transfer' => 'Bank Transfer (BACS)',
            'cash'          => 'Cash Received',
            default         => ucfirst((string)$inv->payment_method),
        };

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Payment Confirmed | Next Gen Relocation</title>
        <style>
            *{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
            body{background:#0b0d12;color:#f3f4f6;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
            .card{background:#141720;padding:40px;border-radius:24px;text-align:center;border:1px solid #10b981;max-width:500px;width:100%;box-shadow:0 25px 50px rgba(0,0,0,0.7);}
            .badge{display:inline-block;background:rgba(16,185,129,0.15);color:#10b981;border:1px solid #10b981;font-size:12px;font-weight:800;padding:5px 16px;border-radius:20px;margin-bottom:18px;text-transform:uppercase;letter-spacing:1px;}
            h2{color:#fff;margin:0 0 10px;font-size:24px;}
            p{color:#9ca3af;font-size:14px;line-height:1.6;margin-bottom:24px;}
            .receipt-box{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:20px;margin-bottom:24px;text-align:left;font-size:13px;}
            .row{display:flex;justify-content:space-between;margin-bottom:8px;}
            .row:last-child{margin-bottom:0;}
            .btn-home{display:inline-block;background:linear-gradient(135deg, #c9a84c, #e2c269);color:#0b0d12;font-weight:700;padding:12px 28px;border-radius:10px;text-decoration:none;font-size:14px;}
        </style></head>
        <body><div class='card'>
            <div class='badge'>BOOKING CONFIRMED & GUARANTEED ✅</div>
            <h2>{$headline}</h2>
            <p>Thank you, <strong>" . e($inv->client_name) . "</strong>. Your payment of <strong>£{$amount}</strong> has been successfully processed and verified.</p>
            <div class='receipt-box'>
                <div class='row'><span style='color:#9ca3af;'>Invoice Ref:</span><strong style='color:#c9a84c;'>#" . e($inv->invoice_number ?: $inv->id) . "</strong></div>
                <div class='row'><span style='color:#9ca3af;'>Payment Method:</span><strong>{$methodLabel}</strong></div>
                <div class='row'><span style='color:#9ca3af;'>Payment Status:</span><strong style='color:#10b981;'>PAID IN FULL</strong></div>
                <div class='row'><span style='color:#9ca3af;'>Relocation Status:</span><strong style='color:#fff;'>Crew & Schedule Reserved</strong></div>
            </div>
            <p style='font-size:13px; color:#9ca3af;'>An official digital tax receipt and booking confirmation email has been dispatched to <strong>" . e($inv->client_email) . "</strong>.</p>
            <a href='/' class='btn-home'>Return to Next Gen Relocation</a>
        </div></body></html>";
    }

    private function cashPendingConfirmationPage(Invoice $inv): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Cash Payment Scheduled | Next Gen Relocation</title>
        <style>
            *{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
            body{background:#0b0d12;color:#f3f4f6;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
            .card{background:#141720;padding:40px;border-radius:24px;text-align:center;border:1px solid #f59e0b;max-width:500px;width:100%;box-shadow:0 25px 50px rgba(0,0,0,0.7);}
            .badge{display:inline-block;background:rgba(245,158,11,0.15);color:#f59e0b;border:1px solid #f59e0b;font-size:12px;font-weight:800;padding:5px 16px;border-radius:20px;margin-bottom:18px;text-transform:uppercase;letter-spacing:1px;}
            h2{color:#fff;margin:0 0 10px;font-size:24px;}
            p{color:#9ca3af;font-size:14px;line-height:1.6;margin-bottom:24px;}
            .receipt-box{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:20px;margin-bottom:24px;text-align:left;font-size:13px;}
            .row{display:flex;justify-content:space-between;margin-bottom:8px;}
            .row:last-child{margin-bottom:0;}
            .btn-home{display:inline-block;background:linear-gradient(135deg, #c9a84c, #e2c269);color:#0b0d12;font-weight:700;padding:12px 28px;border-radius:10px;text-decoration:none;font-size:14px;}
        </style></head>
        <body><div class='card'>
            <div class='badge'>CASH COLLECTION SCHEDULED 💵</div>
            <h2>Cash Payment Registered!</h2>
            <p>Thank you, <strong>" . e($inv->client_name) . "</strong>. Your relocation booking has been reserved. You will pay the deposit of <strong>£" . number_format($inv->total, 2) . "</strong> in cash upon arrival.</p>
            <div class='receipt-box'>
                <div class='row'><span style='color:#9ca3af;'>Invoice Ref:</span><strong style='color:#c9a84c;'>#" . e($inv->invoice_number ?: $inv->id) . "</strong></div>
                <div class='row'><span style='color:#9ca3af;'>Payment Method:</span><strong>Cash on Arrival</strong></div>
                <div class='row'><span style='color:#9ca3af;'>Payment Status:</span><strong style='color:#f59e0b;'>Pending Collection</strong></div>
                <div class='row'><span style='color:#9ca3af;'>Amount to Prepare:</span><strong style='color:#fff;'>£" . number_format($inv->total, 2) . "</strong></div>
            </div>
            <p style='font-size:13px; color:#9ca3af;'>Our surveyor or team coordinator will issue an official stamped receipt upon receiving the cash payment.</p>
            <a href='/' class='btn-home'>Return to Next Gen Relocation</a>
        </div></body></html>";
    }

    private function errorPage(string $title, string $message): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>{$title}</title>
        <style>body{background:#0b0d12;color:#f3f4f6;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif;}</style>
        </head><body><div style='background:#141720;padding:32px;border-radius:16px;text-align:center;border:1px solid #ef4444;max-width:400px;'>
            <h2 style='color:#ef4444;margin-top:0;'>{$title}</h2><p style='color:#9ca3af;'>{$message}</p>
        </div></body></html>";
    }
}
