<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Quotation;
use App\Services\AutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function __construct(private AutomationService $automation)
    {
    }

    private function present(Quotation $q): array
    {
        $inv = \App\Models\Invoice::where('quotation_id', $q->id)
            ->orWhere(function ($query) use ($q) {
                if ($q->lead_id) {
                    $query->where('lead_id', $q->lead_id)->where('status', 'Paid');
                }
            })
            ->orderBy('created_at', 'desc')
            ->first();

        return [
            'id'            => $q->id,
            'leadId'        => $q->lead_id,
            'quoteType'     => $q->quote_type,
            'quoteNumber'   => $q->quote_number,
            'clientName'    => $q->client_name,
            'clientEmail'   => $q->client_email,
            'moveType'      => $q->move_type,
            'from'          => $q->from_location,
            'to'            => $q->to_location,
            'moveDate'      => $q->move_date?->format('Y-m-d'),
            'items'         => $q->items ?: [],
            'packages'      => $q->packages ?: [],
            'selectedPackage' => $q->selected_package,
            'subtotal'           => (float) $q->subtotal,
            'tax'                => (float) $q->tax,
            'total'              => (float) $q->total,
            'depositPercent'     => (float) ($q->deposit_percent ?: 20.00),
            'depositAmount'      => (float) ($q->deposit_amount ?: round($q->total * 0.20, 2)),
            'paymentOption'      => $q->payment_option,
            'initialDepositPaid' => (bool) ($q->initial_deposit_paid),
            'notes'              => $q->notes,
            'status'             => $q->status,
            'validUntil'    => $q->valid_until?->format('Y-m-d'),
            'sentAt'        => $q->sent_at ? \Carbon\Carbon::parse($q->sent_at)->toIso8601String() : null,
            'approvedAt'    => $q->approved_at ? \Carbon\Carbon::parse($q->approved_at)->toIso8601String() : null,
            'declinedAt'    => $q->declined_at ? \Carbon\Carbon::parse($q->declined_at)->toIso8601String() : null,
            'createdAt'     => $q->created_at ? \Carbon\Carbon::parse($q->created_at)->toIso8601String() : null,
            'invoice'       => $inv ? [
                'id'            => $inv->id,
                'invoiceNumber' => $inv->invoice_number,
                'status'        => $inv->status,
                'total'         => (float) $inv->total,
                'serviceTitle'  => $inv->service_title,
            ] : null,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Quotation::query()->whereHas('lead')->orderBy('created_at', 'desc');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->get()->map(fn ($q) => $this->present($q)));
    }

    public function show(string $id): JsonResponse
    {
        $q = Quotation::find($id);
        if (!$q) {
            return response()->json(['error' => 'Quotation not found'], 404);
        }
        return response()->json($this->present($q));
    }

    /**
     * Convert an approved or sent quotation directly into a tax invoice.
     */
    public function convertToInvoice(string $id): JsonResponse
    {
        $q = Quotation::find($id);
        if (!$q) {
            return response()->json(['error' => 'Quotation not found'], 404);
        }

        // Check if an invoice already exists for this quotation
        $existingInv = \App\Models\Invoice::where('quotation_id', $q->id)->first();
        if ($existingInv) {
            return response()->json([
                'message' => 'Invoice already exists for this quotation',
                'invoice' => [
                    'id'            => $existingInv->id,
                    'invoiceNumber' => $existingInv->invoice_number,
                    'status'        => $existingInv->status,
                    'total'         => (float) $existingInv->total,
                ],
            ]);
        }

        $invCount = \App\Models\Invoice::count() + 1001;
        $invNumber = 'INV-' . str_pad((string) $invCount, 5, '0', STR_PAD_LEFT);

        $inv = \App\Models\Invoice::create([
            'id'            => 'INV-' . strtoupper(substr(uniqid(), -6)),
            'lead_id'       => $q->lead_id ?: 'MANUAL',
            'quotation_id'  => $q->id,
            'invoice_number'=> $invNumber,
            'client_name'   => $q->client_name,
            'client_email'  => $q->client_email,
            'service_title' => $q->move_type ? "Relocation Service — {$q->move_type}" : 'Relocation Service',
            'status'        => 'Unpaid',
            'issue_date'    => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(7)->format('Y-m-d'),
            'items'         => $q->items ?: [
                ['label' => 'Relocation Service', 'qty' => 1, 'unit_price' => $q->total, 'amount' => $q->total]
            ],
            'subtotal'      => $q->subtotal ?: round($q->total / 1.2, 2),
            'tax'           => $q->tax ?: round($q->total - ($q->total / 1.2), 2),
            'total'         => $q->total,
        ]);

        return response()->json([
            'message' => 'Invoice generated successfully from quotation',
            'invoice' => [
                'id'            => $inv->id,
                'invoiceNumber' => $inv->invoice_number,
                'status'        => $inv->status,
                'total'         => (float) $inv->total,
                'clientName'    => $inv->client_name,
                'dueDate'       => $inv->due_date?->format('Y-m-d'),
            ],
        ], 201);
    }

    public function store(Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        [$subtotal, $tax, $total] = $this->totals($items, (float) $request->input('taxRate', 0.20));

        $q = Quotation::create([
            'id'            => 'Q-' . strtoupper(substr(uniqid(), -6)),
            'lead_id'       => $request->input('leadId'),
            'quote_number'  => 'QT-' . str_pad((string) (Quotation::count() + 1001), 5, '0', STR_PAD_LEFT),
            'client_name'   => $request->input('clientName', ''),
            'client_email'  => $request->input('clientEmail', ''),
            'move_type'     => $request->input('moveType'),
            'from_location' => $request->input('from'),
            'to_location'   => $request->input('to'),
            'move_date'     => $request->input('moveDate'),
            'items'         => $items,
            'packages'      => $request->input('packages', []),
            'selected_package'=> $request->input('selectedPackage'),
            'subtotal'      => $subtotal,
            'tax'           => $tax,
            'total'         => $total,
            'notes'         => $request->input('notes'),
            'status'        => 'draft',
            'valid_until'   => $request->input('validUntil', now()->addDays(14)->format('Y-m-d')),
        ]);



        return response()->json($this->present($q), 201);
    }

    /**
     * Admin edits the quotation fields before sending.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $q = Quotation::find($id);
        if (!$q) {
            return response()->json(['error' => 'Quotation not found'], 404);
        }

        $data = array_filter([
            'client_name'   => $request->input('clientName'),
            'client_email'  => $request->input('clientEmail'),
            'move_type'     => $request->input('moveType'),
            'from_location' => $request->input('from'),
            'to_location'   => $request->input('to'),
            'move_date'     => $request->input('moveDate'),
            'packages'      => $request->input('packages'),
            'selected_package'=> $request->input('selectedPackage'),
            'notes'         => $request->input('notes'),
            'valid_until'   => $request->input('validUntil'),
        ], fn ($v) => $v !== null);

        if ($request->has('items')) {
            $items = $request->input('items', []);
            [$subtotal, $tax, $total] = $this->totals($items, (float) $request->input('taxRate', 0.20));
            $data['items']    = $items;
            $data['subtotal'] = $subtotal;
            $data['tax']      = $tax;
            $data['total']    = $total;
        }

        $q->update($data);



        return response()->json($this->present($q->refresh()));
    }

    /**
     * Admin sends the quotation email (with approve link) and arms reminders.
     */
    public function send(string $id): JsonResponse
    {
        $q = Quotation::find($id);
        if (!$q) {
            return response()->json(['error' => 'Quotation not found'], 404);
        }

        $q = $this->automation->sendQuotation($q);
        
        \App\Models\AppNotification::create([
            'user_id' => null,
            'type' => 'success',
            'title' => 'Quotation Sent',
            'message' => 'Quotation for lead ' . $q->lead_id . ' has been sent.',
            'link' => '/app/quotations?view=' . $q->id,
            'is_read' => false,
        ]);



        return response()->json([
            'message'   => 'Quotation sent to customer.',
            'quotation' => $this->present($q),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $q = Quotation::find($id);
        if (!$q) {
            return response()->json(['error' => 'Quotation not found'], 404);
        }
        $q->delete();



        return response()->json(['message' => 'Quotation deleted successfully', 'id' => $id]);
    }

    /**
     * Public approve link target (clicked by customer from quotation email).
     * Supports both Initial Quotation (Pay Later vs Pay 20% Now) and Post-Survey Final Quotation (3 Plans with 20% finance catch).
     */
    public function publicApprove(Request $request)
    {
        $quoteId = $request->query('quote_id');
        $action  = $request->query('action');   // 'approve_pay_later' | 'approve_pay_now'
        $package = $request->query('package');  // 'Basic', 'Standard', 'Premium'
        $q = Quotation::find($quoteId);

        if (!$q) {
            return response($this->confirmationPage('Quotation Not Found', 'We could not find this quotation. Please contact support.'), 404)
                ->header('Content-Type', 'text/html');
        }

        $isPostSurvey = $q->quote_type === 'post-survey';
        $lead = $q->lead_id ? Lead::find($q->lead_id) : null;

        // ═════════════════════════════════════════════════════════════════════
        //  1. INITIAL QUOTATION FLOW
        // ═════════════════════════════════════════════════════════════════════
        if (!$isPostSurvey) {
            // If customer has not chosen an action yet, render Interactive Initial Quotation Page
            if (!$action && $q->status !== 'approved') {
                return response($this->renderInitialQuoteApprovalPage($q))->header('Content-Type', 'text/html');
            }

            // Client chose: Option A — Approve & Pay Later (After Survey)
            if ($action === 'approve_pay_later') {
                $q->update([
                    'status'               => 'approved',
                    'approved_at'          => now(),
                    'payment_option'       => 'pay_later',
                    'initial_deposit_paid' => false,
                ]);

                if ($lead) {
                    $lead->update([
                        'status' => 'quote-approved',
                        'stage' => 'Survey',
                        'quotation_status' => 'approved',
                        'quotation_approved_at' => now(),
                    ]);

                    \App\Models\AppNotification::create([
                        'user_id' => null,
                        'type'    => 'info',
                        'title'   => 'Initial Quote Approved (Pay Later)',
                        'message' => "Client {$lead->name} approved initial quote (#{$q->quote_number}) and chose to pay after survey.",
                        'link'    => '/app/quotations?view=' . $q->id,
                        'is_read' => false,
                    ]);

                    $this->automation->recordResponse($lead, 'quotation');
                }



                $bookSurveyUrl = "/book-survey?lead_id=" . ($lead?->id ?: '');
                $html = $this->confirmationPage(
                    'Initial Quotation Approved ✅',
                    "Thank you, <strong>" . e($q->client_name) . "</strong>! You have approved your initial quotation and chosen to <strong>pay after your pre-move survey</strong>.<br><br>
                    Our relocation specialists will now conduct your pre-move survey to assess your inventory and produce your accurate final quotation.<br><br>
                    <a href='{$bookSurveyUrl}' style='display:inline-block; background:linear-gradient(135deg, #c9a84c, #e2c269); color:#0b0d12; font-weight:800; padding:12px 24px; border-radius:10px; text-decoration:none; margin-top:10px;'>
                        📅 Schedule Pre-Move Survey Now
                    </a>"
                );
                return response($html)->header('Content-Type', 'text/html');
            }

            // Client chose: Option B — Approve & Pay 20% Deposit Now
            if ($action === 'approve_pay_now') {
                $depositAmount = round((float) $q->total * 0.20, 2);

                $q->update([
                    'status'          => 'approved',
                    'approved_at'     => now(),
                    'payment_option'  => 'pay_now',
                    'deposit_percent' => 20.00,
                    'deposit_amount'  => $depositAmount,
                ]);

                // Create or retrieve 20% initial deposit invoice
                $invoice = \App\Models\Invoice::firstOrCreate(
                    [
                        'quotation_id' => $q->id,
                        'invoice_type' => 'initial_deposit',
                    ],
                    [
                        'id'             => 'INV-' . strtoupper(substr(uniqid(), -6)),
                        'lead_id'        => $q->lead_id ?: 'MANUAL',
                        'invoice_number' => 'INV-' . str_pad((string)(\App\Models\Invoice::count() + 1001), 5, '0', STR_PAD_LEFT),
                        'client_name'    => $q->client_name,
                        'client_email'   => $q->client_email,
                        'service_title'  => '20% Initial Booking Deposit — ' . ($q->move_type ?: 'Relocation'),
                        'status'         => 'Unpaid',
                        'issue_date'     => now()->format('Y-m-d'),
                        'due_date'       => now()->addDays(7)->format('Y-m-d'),
                        'items'          => [
                            [
                                'description' => '20% Initial Booking Deposit (' . ($q->move_type ?: 'Relocation') . ')',
                                'amount'      => $depositAmount,
                            ]
                        ],
                        'subtotal'       => $depositAmount,
                        'tax'            => 0,
                        'total'          => $depositAmount,
                        'paid_amount'    => 0,
                        'balance_due'    => $depositAmount,
                    ]
                );

                if ($lead) {
                    $lead->update([
                        'invoice_issued_at' => now(),
                    ]);
                }



                // Redirect client directly to the 3-Method Payment Portal
                return redirect("/payment/checkout?invoice_id={$invoice->id}");
            }

            // If already approved, inform client
            if ($q->status === 'approved') {
                return response($this->confirmationPage(
                    'Quotation Already Approved',
                    "This quotation has already been approved."
                ))->header('Content-Type', 'text/html');
            }
        }

        // ═════════════════════════════════════════════════════════════════════
        //  2. POST-SURVEY FINAL QUOTATION FLOW (3 Packages + 20% Catch)
        // ═════════════════════════════════════════════════════════════════════
        if ($isPostSurvey) {
            // If customer has not chosen a package yet, render the 3-Plan Selection Page
            if (!$package && $q->status !== 'approved') {
                return response($this->renderPlanSelectionPage($q))->header('Content-Type', 'text/html');
            }

            // Customer selected a package (Basic, Standard, or Premium)
            $packageTotal = (float) $q->total;
            if ($package && is_array($q->packages)) {
                foreach ($q->packages as $pkg) {
                    if (strcasecmp($pkg['name'] ?? '', $package) === 0) {
                        $packageTotal = (float) ($pkg['total'] ?? $q->total);
                        break;
                    }
                }
            }

            $q->update([
                'status'           => 'approved',
                'approved_at'      => now(),
                'selected_package' => $package ?: 'Standard',
            ]);

            // ── The Finance Catch: Check if initial 20% was already paid ──
            $initialDepositPaid = (bool) ($lead?->initial_deposit_paid || $q->initial_deposit_paid);
            $initialDepositAmount = (float) ($lead?->total_deposit_paid ?: ($q->deposit_amount ?: 0));

            if ($initialDepositPaid) {
                // If initial 20% was paid, charge 20% MORE according to the selected plan
                $additionalDepositAmount = round($packageTotal * 0.20, 2);
                $totalDepositAfterThis   = round($initialDepositAmount + $additionalDepositAmount, 2);
                $remainingBalance        = max(0, round($packageTotal - $totalDepositAfterThis, 2));

                $invoice = \App\Models\Invoice::create([
                    'id'             => 'INV-' . strtoupper(substr(uniqid(), -6)),
                    'lead_id'        => $q->lead_id ?: 'MANUAL',
                    'quotation_id'   => $q->id,
                    'invoice_number' => 'INV-' . str_pad((string)(\App\Models\Invoice::count() + 1001), 5, '0', STR_PAD_LEFT),
                    'client_name'    => $q->client_name,
                    'client_email'   => $q->client_email,
                    'service_title'  => "Additional 20% Confirmation Deposit — " . ($package ?: 'Selected') . " Plan",
                    'invoice_type'   => 'plan_deposit',
                    'status'         => 'Unpaid',
                    'issue_date'     => now()->format('Y-m-d'),
                    'due_date'       => now()->addDays(7)->format('Y-m-d'),
                    'items'          => [
                        [
                            'description' => "Additional 20% Plan Confirmation Deposit (" . ($package ?: 'Selected Plan') . ")",
                            'amount'      => $additionalDepositAmount,
                        ]
                    ],
                    'subtotal'       => $additionalDepositAmount,
                    'tax'            => 0,
                    'total'          => $additionalDepositAmount,
                    'paid_amount'    => 0,
                    'balance_due'    => $remainingBalance,
                ]);
            } else {
                // Initial 20% was NOT paid (they chose Pay Later initially)
                $standardDepositAmount = round($packageTotal * 0.20, 2);
                $remainingBalance      = round($packageTotal * 0.80, 2);

                $invoice = \App\Models\Invoice::create([
                    'id'             => 'INV-' . strtoupper(substr(uniqid(), -6)),
                    'lead_id'        => $q->lead_id ?: 'MANUAL',
                    'quotation_id'   => $q->id,
                    'invoice_number' => 'INV-' . str_pad((string)(\App\Models\Invoice::count() + 1001), 5, '0', STR_PAD_LEFT),
                    'client_name'    => $q->client_name,
                    'client_email'   => $q->client_email,
                    'service_title'  => "20% Advance Booking Deposit — " . ($package ?: 'Selected') . " Plan",
                    'invoice_type'   => 'plan_deposit',
                    'status'         => 'Unpaid',
                    'issue_date'     => now()->format('Y-m-d'),
                    'due_date'       => now()->addDays(7)->format('Y-m-d'),
                    'items'          => [
                        [
                            'description' => "20% Advance Booking Deposit (" . ($package ?: 'Selected Plan') . ")",
                            'amount'      => $standardDepositAmount,
                        ]
                    ],
                    'subtotal'       => $standardDepositAmount,
                    'tax'            => 0,
                    'total'          => $standardDepositAmount,
                    'paid_amount'    => 0,
                    'balance_due'    => $remainingBalance,
                ]);
            }

            if ($lead) {
                $lead->update(['invoice_issued_at' => now()]);
            }



            // Redirect client directly to the 3-Method Payment Portal
            return redirect("/payment/checkout?invoice_id={$invoice->id}");
        }

        return response($this->confirmationPage(
            'Quotation Already Approved',
            "This quotation has already been approved."
        ))->header('Content-Type', 'text/html');
    }

    private function totals(array $items, float $taxRate): array
    {
        $subtotal = 0.0;
        foreach ($items as $it) {
            $subtotal += (float) ($it['amount'] ?? 0);
        }
        $subtotal = round($subtotal, 2);
        $tax = round($subtotal * $taxRate, 2);
        $total = round($subtotal + $tax, 2);

        return [$subtotal, $tax, $total];
    }

    /**
     * Render Initial Quotation Approval Page with the Two Clear Choices (Pay Later vs Pay 20% Now).
     */
    private function renderInitialQuoteApprovalPage(Quotation $q): string
    {
        $clientName = e($q->client_name ?: 'Valued Customer');
        $quoteNum   = e($q->quote_number ?: $q->id);
        $moveType   = e($q->move_type ?: 'Relocation Service');
        $from       = e($q->from_location ?: 'Origin');
        $to         = e($q->to_location ?: 'Destination');
        $moveDate   = $q->move_date ? \Carbon\Carbon::parse($q->move_date)->format('d F Y') : 'TBC';
        $totalCost  = number_format((float)$q->total, 2);
        $deposit20  = number_format(round((float)$q->total * 0.20, 2), 2);
        $balance    = number_format(round((float)$q->total * 0.80, 2), 2);

        // Build itemized rows
        $itemsHtml = '';
        if (is_array($q->items) && !empty($q->items)) {
            foreach ($q->items as $it) {
                $lbl = e($it['label'] ?? 'Removal Service');
                $amt = number_format((float)($it['amount'] ?? 0), 2);
                $itemsHtml .= "<tr><td style='padding:10px 0; color:#d1d5db; border-bottom:1px solid rgba(255,255,255,0.06);'>{$lbl}</td><td style='padding:10px 0; text-align:right; font-weight:600; color:#fff; border-bottom:1px solid rgba(255,255,255,0.06);'>£{$amt}</td></tr>";
            }
        } else {
            $itemsHtml = "<tr><td style='padding:10px 0; color:#d1d5db;'>Relocation Moving Service</td><td style='padding:10px 0; text-align:right; font-weight:600; color:#fff;'>£{$totalCost}</td></tr>";
        }

        $payLaterUrl = "/quotation/approve?quote_id={$q->id}&action=approve_pay_later";
        $payNowUrl   = "/quotation/approve?quote_id={$q->id}&action=approve_pay_now";

        return "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Initial Relocation Quotation — {$quoteNum} | Next Gen Relocation</title>
    <style>
        * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { background: #0b0d12; color: #f3f4f6; margin: 0; padding: 40px 20px; min-height: 100vh; display: flex; justify-content: center; }
        .quote-box { background: #141720; border: 1px solid rgba(201,168,76,0.3); border-radius: 24px; max-width: 780px; width: 100%; padding: 40px; box-shadow: 0 30px 60px rgba(0,0,0,0.8); }
        .logo { color: #c9a84c; font-size: 22px; font-weight: 800; letter-spacing: 1px; text-align: center; }
        h1 { font-size: 26px; text-align: center; margin: 8px 0 4px; color: #fff; }
        p.sub { font-size: 14px; text-align: center; color: #9ca3af; margin: 0 0 28px; }
        .badge-bar { display: flex; justify-content: center; gap: 12px; margin-bottom: 24px; }
        .tag { background: rgba(201,168,76,0.12); color: #c9a84c; border: 1px solid rgba(201,168,76,0.3); font-size: 11px; font-weight: 800; padding: 4px 14px; border-radius: 20px; text-transform: uppercase; }
        
        .summary-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 20px; margin-bottom: 24px; }
        .route-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; font-size: 13px; margin-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 16px; }
        .route-item span { color: #9ca3af; display: block; font-size: 11px; text-transform: uppercase; margin-bottom: 4px; }
        .route-item strong { color: #fff; font-size: 14px; }
        
        table { width: 100%; font-size: 13px; border-collapse: collapse; }
        .totals-row { display: flex; justify-content: space-between; padding-top: 14px; border-top: 1px dashed rgba(255,255,255,0.15); margin-top: 14px; }
        .est-total { font-size: 24px; font-weight: 800; color: #c9a84c; }
        
        .deposit-highlight { background: rgba(201,168,76,0.08); border: 1px dashed rgba(201,168,76,0.3); border-radius: 14px; padding: 18px; margin: 24px 0; text-align: center; }
        .deposit-amt { font-size: 28px; font-weight: 800; color: #10b981; margin: 6px 0; }
        
        .choice-section { margin-top: 32px; }
        .choice-title { text-align: center; font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px; }
        .choice-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .choice-card { background: #1c202d; border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; padding: 24px; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s; }
        .choice-card:hover { border-color: #c9a84c; transform: translateY(-2px); }
        .choice-card.popular { border: 2px solid #c9a84c; position: relative; box-shadow: 0 10px 25px rgba(201,168,76,0.15); }
        .pop-badge { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #c9a84c; color: #0b0d12; font-size: 10px; font-weight: 800; padding: 3px 12px; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .choice-card h3 { margin: 0 0 8px; font-size: 18px; color: #fff; }
        .choice-card p { font-size: 13px; color: #9ca3af; line-height: 1.5; margin: 0 0 20px; flex-grow: 1; }
        
        .btn-later { display: block; text-align: center; background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.2); padding: 14px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; transition: all 0.2s; }
        .btn-later:hover { background: rgba(255,255,255,0.15); }
        .btn-now { display: block; text-align: center; background: linear-gradient(135deg, #c9a84c, #e2c269); color: #0b0d12; border: none; padding: 14px; border-radius: 12px; font-weight: 800; font-size: 14px; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 15px rgba(201,168,76,0.3); }
        .btn-now:hover { opacity: 0.95; transform: translateY(-1px); }
        
        @media(max-width: 640px) {
            .choice-grid { grid-template-columns: 1fr; }
            .route-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class='quote-box'>
        <div class='logo'>✨ NEXT GEN RELOCATION LTD</div>
        <h1>Indicative Relocation Quotation</h1>
        <p class='sub'>Prepared exclusively for <strong>{$clientName}</strong> · Quote Reference <strong>{$quoteNum}</strong></p>

        <div class='badge-bar'>
            <span class='tag'>Initial Estimate</span>
            <span class='tag'>{$moveType}</span>
            <span class='tag'>Move Date: {$moveDate}</span>
        </div>

        <div class='summary-card'>
            <div class='route-grid'>
                <div class='route-item'><span>Collection Address:</span><strong>{$from}</strong></div>
                <div class='route-item'><span>Delivery Destination:</span><strong>{$to}</strong></div>
            </div>

            <table>
                {$itemsHtml}
            </table>

            <div class='totals-row'>
                <div>
                    <span style='color:#9ca3af; font-size:12px; text-transform:uppercase;'>Total Estimated Cost (inc VAT)</span>
                    <div class='est-total'>£{$totalCost}</div>
                </div>
                <div style='text-align:right;'>
                    <span style='color:#9ca3af; font-size:12px; text-transform:uppercase;'>Balance Due on Move Day</span>
                    <div style='font-size:20px; font-weight:700; color:#fff;'>£{$balance}</div>
                </div>
            </div>
        </div>

        <div class='deposit-highlight'>
            <span style='color:#9ca3af; font-size:12px; text-transform:uppercase; font-weight:bold;'>Required 20% Booking Deposit</span>
            <div class='deposit-amt'>£{$deposit20}</div>
            <div style='font-size:13px; color:#d1d5db;'>Secures your removal crew and guarantees vehicle availability for your move date.</div>
        </div>

        <div class='choice-section'>
            <div class='choice-title'>Select Your Preferred Approval Option</div>
            <div class='choice-grid'>
                <!-- Option 1: Approve & Pay Later -->
                <div class='choice-card'>
                    <div>
                        <h3>Option A: Approve & Pay Later</h3>
                        <p>Approve this initial quotation now with <strong>£0 paid today</strong>. We will arrange your pre-move survey, assess your volume, and you can pay the deposit after receiving your final post-survey packages.</p>
                    </div>
                    <a href='{$payLaterUrl}' class='btn-later'>
                        ✓ Approve & Pay Later (After Survey)
                    </a>
                </div>

                <!-- Option 2: Approve & Pay Now -->
                <div class='choice-card popular'>
                    <div class='pop-badge'>GUARANTEE DATE NOW</div>
                    <div>
                        <h3>Option B: Approve & Pay 20% Deposit</h3>
                        <p>Pay the 20% deposit (<strong>£{$deposit20}</strong>) right away using Card (Stripe), Bank Transfer, or Cash. Locks in your removal date and gives you priority crew scheduling.</p>
                    </div>
                    <a href='{$payNowUrl}' class='btn-now'>
                        💳 Approve & Pay £{$deposit20} Deposit Now ➔
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";
    }

    /**
     * Render Post-Survey 3-Plan Selection Page with 20% Finance Catch logic.
     */
    private function renderPlanSelectionPage(Quotation $q): string
    {
        $packages = is_array($q->packages) && !empty($q->packages) ? $q->packages : [];
        if (empty($packages)) {
            $base = (float)($q->subtotal ?: ($q->total / 1.2));
            if ($base <= 0) $base = 1500.0;
            $bSub = round($base * 0.85, 2); $bTot = round($bSub * 1.20, 2);
            $sSub = round($base * 1.15, 2); $sTot = round($sSub * 1.20, 2);
            $pSub = round($base * 1.50, 2); $pTot = round($pSub * 1.20, 2);
            $packages = [
                [
                    'name'        => 'Basic',
                    'tagline'     => 'Self-Packing & Standard Removal',
                    'subtotal'    => $bSub, 'tax' => round($bSub * 0.20, 2), 'total' => $bTot,
                    'deposit_20'  => round($bTot * 0.20, 2),
                    'features'    => ['Professional Removal Van & Driver', 'Standard Loading & Transport', 'Basic Goods-in-Transit Insurance', 'Self-Packing by Customer'],
                ],
                [
                    'name'        => 'Standard',
                    'tagline'     => 'Full Service & Dismantling (Recommended)',
                    'subtotal'    => $sSub, 'tax' => round($sSub * 0.20, 2), 'total' => $sTot,
                    'deposit_20'  => round($sTot * 0.20, 2),
                    'features'    => ['Professional Removal Van & Full Crew', 'Full Furniture Dismantling & Reassembly', 'Loading, Transport & Unloading', 'Protective Blankets & Straps', 'Standard Transit Insurance'],
                ],
                [
                    'name'        => 'Premium',
                    'tagline'     => 'White-Glove VIP Full Relocation',
                    'subtotal'    => $pSub, 'tax' => round($pSub * 0.20, 2), 'total' => $pTot,
                    'deposit_20'  => round($pTot * 0.20, 2),
                    'features'    => ['Dedicated VIP Removal Crew & Luton Vans', 'Full Packing & Unpacking Service', 'Complete Furniture Dismantling & Reassembly', 'Fragile & Fine Art Wrapping', 'Full-Value Comprehensive Insurance', 'Priority Time Window'],
                ],
            ];
            $q->update(['packages' => $packages]);
        }

        $lead = $q->lead_id ? Lead::find($q->lead_id) : null;
        $initialDepositPaid = (bool) ($lead?->initial_deposit_paid || $q->initial_deposit_paid);
        $initialPaidAmt = (float) ($lead?->total_deposit_paid ?: ($q->deposit_amount ?: 0));

        $cardsHtml = '';
        foreach ($packages as $pkg) {
            $name       = e($pkg['name'] ?? 'Plan');
            $tagline    = e($pkg['tagline'] ?? '');
            $planTotal  = (float) ($pkg['total'] ?? $q->total);
            $features   = is_array($pkg['features'] ?? null) ? $pkg['features'] : [];
            $isRecommended = strcasecmp($name, 'Standard') === 0;

            $featList = '';
            foreach ($features as $f) {
                $featList .= "<li style='margin-bottom:8px; display:flex; align-items:center; gap:8px;'><span style='color:#c9a84c;'>✓</span> " . e($f) . "</li>";
            }

            if ($initialDepositPaid) {
                // ── Finance Catch: 20% already paid! Charge 20% more according to plan total ──
                $depositNow = round($planTotal * 0.20, 2);
                $totalPaidAfter = round($initialPaidAmt + $depositNow, 2);
                $balanceOnDay   = max(0, round($planTotal - $totalPaidAfter, 2));

                $pricingBlock = "
                    <div style='background:rgba(201,168,76,0.08); border:1px dashed rgba(201,168,76,0.25); border-radius:12px; padding:14px; margin-bottom:20px;'>
                        <div style='font-size:11px; color:#9ca3af; text-transform:uppercase;'>Total Package Price</div>
                        <div style='font-size:26px; font-weight:800; color:#c9a84c; margin:2px 0;'>£" . number_format($planTotal, 2) . "</div>
                        
                        <div style='border-top:1px solid rgba(255,255,255,0.08); margin:10px 0; padding-top:10px; font-size:12px; color:#9ca3af;'>
                            <div style='display:flex; justify-content:space-between; margin-bottom:4px;'>
                                <span>Initial 20% Deposit Paid:</span>
                                <strong style='color:#10b981;'>£" . number_format($initialPaidAmt, 2) . " (Paid ✓)</strong>
                            </div>
                            <div style='display:flex; justify-content:space-between; margin-bottom:4px;'>
                                <span style='color:#fff; font-weight:bold;'>Additional 20% Due Now:</span>
                                <strong style='color:#c9a84c; font-size:14px;'>£" . number_format($depositNow, 2) . "</strong>
                            </div>
                            <div style='display:flex; justify-content:space-between;'>
                                <span>Final Balance on Move Day:</span>
                                <strong style='color:#fff;'>£" . number_format($balanceOnDay, 2) . "</strong>
                            </div>
                        </div>
                    </div>
                ";
                $btnText = "Select {$name} & Pay Additional 20% (£" . number_format($depositNow, 2) . ")";
            } else {
                // Standard 20% deposit due now, 80% remaining balance
                $depositNow = round($planTotal * 0.20, 2);
                $balanceOnDay = round($planTotal * 0.80, 2);

                $pricingBlock = "
                    <div style='background:rgba(201,168,76,0.08); border:1px dashed rgba(201,168,76,0.25); border-radius:12px; padding:14px; margin-bottom:20px;'>
                        <div style='font-size:11px; color:#9ca3af; text-transform:uppercase;'>Total Relocation Cost</div>
                        <div style='font-size:26px; font-weight:800; color:#c9a84c; margin:2px 0;'>£" . number_format($planTotal, 2) . "</div>
                        <div style='font-size:13px; color:#f3f4f6; margin-top:4px;'>🔒 20% Deposit to Book: <strong style='color:#10b981;'>£" . number_format($depositNow, 2) . "</strong></div>
                        <div style='font-size:11px; color:#9ca3af; margin-top:2px;'>Remaining balance on move day: £" . number_format($balanceOnDay, 2) . "</div>
                    </div>
                ";
                $btnText = "Select {$name} & Pay 20% Deposit (£" . number_format($depositNow, 2) . ")";
            }

            $cardsHtml .= "
                <div style='background:#141720; border:" . ($isRecommended ? '2px solid #c9a84c' : '1px solid rgba(255,255,255,0.1)') . "; border-radius:18px; padding:28px 24px; position:relative; flex:1; min-width:280px; max-width:360px; display:flex; flex-direction:column; justify-content:space-between; box-shadow: " . ($isRecommended ? '0 10px 30px rgba(201,168,76,0.15)' : 'none') . ";'>
                    " . ($isRecommended ? "<div style='position:absolute; top:-14px; left:50%; transform:translateX(-50%); background:#c9a84c; color:#0b0d12; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; padding:4px 14px; border-radius:20px;'>MOST POPULAR</div>" : "") . "
                    <div>
                        <h3 style='margin:0 0 6px; font-size:22px; color:#ffffff;'>{$name} Plan</h3>
                        <p style='color:#9ca3af; font-size:13px; margin:0 0 20px; min-height:36px;'>{$tagline}</p>
                        {$pricingBlock}
                        <ul style='list-style:none; padding:0; margin:0 0 24px; font-size:14px; color:#d1d5db;'>
                            {$featList}
                        </ul>
                    </div>
                    <a href='/api/quotation/approve?quote_id={$q->id}&package=" . urlencode($name) . "' 
                       style='background:" . ($isRecommended ? 'linear-gradient(135deg, #c9a84c, #e2c269)' : 'rgba(255,255,255,0.08)') . "; color:" . ($isRecommended ? '#0b0d12' : '#ffffff') . "; border:" . ($isRecommended ? 'none' : '1px solid rgba(255,255,255,0.2)') . "; width:100%; display:inline-block; text-align:center; padding:12px; border-radius:10px; font-weight:700; font-size:14px; text-decoration:none; transition:all 0.2s;'>
                        {$btnText}
                    </a>
                </div>
            ";
        }

        $clientName = e($q->client_name ?: 'Customer');
        $from       = e($q->from_location ?: 'Origin');
        $to         = e($q->to_location ?: 'Destination');

        $depositNotice = $initialDepositPaid ? "
            <div style='background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); border-radius:12px; padding:12px 20px; max-width:700px; margin:16px auto 0; font-size:13px; color:#34d399; text-align:center;'>
                🎉 <strong>Initial 20% Deposit (£" . number_format($initialPaidAmt, 2) . ") Paid!</strong> Selecting your final plan charges the additional 20% plan confirmation deposit. The remaining 60% is settled on move day.
            </div>
        " : "";

        return "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Select Relocation Plan | Next Gen Relocation</title>
            <style>
                *{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
                body{background:#0b0d12;color:#f3f4f6;margin:0;padding:40px 20px;min-height:100vh;}
                .container{max-width:1140px;margin:0 auto;}
                .header{text-align:center;margin-bottom:30px;}
                .logo{color:#c9a84c;font-size:24px;font-weight:800;letter-spacing:1px;margin-bottom:8px;}
                h1{font-size:30px;margin:0 0 10px;color:#ffffff;}
                p.sub{color:#9ca3af;font-size:15px;margin:0;}
                .route{display:inline-flex;align-items:center;gap:10px;background:#141720;border:1px solid rgba(255,255,255,0.1);padding:8px 20px;border-radius:20px;margin-top:16px;font-size:14px;color:#c9a84c;}
                .plans{display:flex;flex-wrap:wrap;gap:24px;justify-content:center;margin-top:30px;}
            </style></head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <div class='logo'>✨ NEXT GEN RELOCATION</div>
                        <h1>Post-Survey Quotation & Relocation Plans</h1>
                        <p class='sub'>Hello <strong>{$clientName}</strong>, please review your post-survey relocation plans and select your package to complete booking.</p>
                        <div class='route'>📍 Move Route: <strong>{$from}</strong> ➔ <strong>{$to}</strong></div>
                        {$depositNotice}
                    </div>
                    <div class='plans'>
                        {$cardsHtml}
                    </div>
                </div>
            </body></html>";
    }

    private function confirmationPage(string $title, string $message): string
    {
        $title = e($title);
        return "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title} | Next Gen Relocation</title>
            <style>
                *{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
                body{background:#0b0d12;color:#f3f4f6;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
                .card{background:#141720;border:1px solid rgba(201,168,76,.3);border-radius:20px;max-width:480px;width:100%;padding:40px 32px;text-align:center;box-shadow:0 20px 40px rgba(0,0,0,.6);}
                .logo{color:#c9a84c;font-size:22px;font-weight:700;margin-bottom:16px;}
                h2{margin:0 0 12px;font-size:22px;}
                p{color:#9ca3af;font-size:15px;line-height:1.6;}
            </style></head>
            <body><div class='card'>
                <div class='logo'>✨ Next Gen Relocation</div>
                <h2>{$title}</h2>
                <p>{$message}</p>
            </div></body></html>";
    }
}
