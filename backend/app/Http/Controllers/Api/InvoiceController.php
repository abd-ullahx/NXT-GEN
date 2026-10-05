<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    private function present(Invoice $inv): array
    {
        return [
            'id'               => $inv->id,
            'leadId'           => $inv->lead_id,
            'quotationId'      => $inv->quotation_id,
            'invoiceNumber'    => $inv->invoice_number,
            'clientName'       => $inv->client_name,
            'clientEmail'      => $inv->client_email,
            'serviceTitle'     => $inv->service_title,
            'invoiceType'      => $inv->invoice_type ?: 'standard',
            'status'           => $inv->status,
            'paymentMethod'    => $inv->payment_method,
            'issueDate'        => $inv->issue_date?->format('Y-m-d'),
            'dueDate'          => $inv->due_date?->format('Y-m-d'),
            'items'            => $inv->items ?: [],
            'subtotal'         => (float) $inv->subtotal,
            'tax'              => (float) $inv->tax,
            'total'            => (float) $inv->total,
            'paidAmount'       => (float) ($inv->paid_amount ?: ($inv->status === 'Paid' ? $inv->total : 0)),
            'balanceDue'       => (float) ($inv->balance_due ?: ($inv->status === 'Paid' ? 0 : $inv->total)),
            'paymentReference' => $inv->payment_reference,
            'paidAt'           => $inv->paid_at ? \Carbon\Carbon::parse($inv->paid_at)->toIso8601String() : null,
            'createdAt'        => $inv->created_at ? \Carbon\Carbon::parse($inv->created_at)->toIso8601String() : null,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->orderBy('created_at', 'desc');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        return response()->json($query->get()->map(fn ($inv) => $this->present($inv)));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'client_name'   => 'required|string',
            'client_email'  => 'required|email',
            'service_title' => 'nullable|string',
            'items'         => 'nullable|array',
            'due_date'      => 'nullable|date',
        ]);

        $items = $request->input('items', []);
        $subtotal = 0.0;
        foreach ($items as $item) {
            $qty = (float)($item['quantity'] ?? $item['qty'] ?? 1);
            $unit = (float)($item['unitPrice'] ?? $item['unit_price'] ?? $item['amount'] ?? 0);
            $subtotal += ($qty * $unit);
        }
        $subtotal = round($subtotal, 2);
        $tax = round($subtotal * 0.20, 2);
        $total = round($subtotal + $tax, 2);

        $invCount = Invoice::count() + 1001;
        $invNumber = 'INV-' . str_pad((string)$invCount, 5, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            'id'             => 'INV-' . strtoupper(substr(uniqid(), -6)),
            'lead_id'        => $request->input('lead_id', 'MANUAL'),
            'quotation_id'   => $request->input('quotation_id'),
            'invoice_number' => $invNumber,
            'client_name'    => $request->input('client_name'),
            'client_email'   => $request->input('client_email'),
            'service_title'  => $request->input('service_title', 'Relocation Service'),
            'invoice_type'   => $request->input('invoice_type', 'standard'),
            'status'         => 'Unpaid',
            'issue_date'     => now()->format('Y-m-d'),
            'due_date'       => $request->input('due_date', now()->addDays(14)->format('Y-m-d')),
            'items'          => $items,
            'subtotal'       => $subtotal,
            'tax'            => $tax,
            'total'          => $total,
            'paid_amount'    => 0.00,
            'balance_due'    => $total,
        ]);

        return response()->json($this->present($invoice), 201);
    }

    public function show(string $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) return response()->json(['error' => 'Invoice not found'], 404);
        return response()->json($this->present($invoice));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) return response()->json(['error' => 'Invoice not found'], 404);

        $request->validate([
            'client_name'   => 'sometimes|string',
            'client_email'  => 'sometimes|email',
            'service_title' => 'sometimes|string',
            'invoice_type'  => 'sometimes|string',
            'status'        => 'sometimes|string',
            'issue_date'    => 'sometimes|date',
            'due_date'      => 'sometimes|date',
            'items'         => 'sometimes|array',
            'subtotal'      => 'sometimes|numeric',
            'tax'           => 'sometimes|numeric',
            'total'         => 'sometimes|numeric',
        ]);

        $invoice->update($request->all());

        return response()->json($this->present($invoice->refresh()));
    }

    public function send(string $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) return response()->json(['error' => 'Invoice not found'], 404);

        $invoice->update(['status' => 'Unpaid']);

        return response()->json(['message' => 'Invoice sent to customer.', 'invoice' => $this->present($invoice->refresh())]);
    }

    /**
     * Record payment for an invoice and sync directly into general ledger transactions.
     * Used by Admin or Driver in CRM panel (especially for manual Cash recording).
     */
    public function recordPayment(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) return response()->json(['error' => 'Invoice not found'], 404);

        $request->validate([
            'amount'         => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
            'date'           => 'nullable|date',
            'reference'      => 'nullable|string',
            'category'       => 'nullable|string',
        ]);

        $amount        = (float) ($request->input('amount') ?: $invoice->total);
        $paymentMethod = strtolower($request->input('payment_method', 'cash'));
        if (str_contains($paymentMethod, 'bank')) {
            $normalizedMethod = 'bank_transfer';
        } elseif (str_contains($paymentMethod, 'stripe') || str_contains($paymentMethod, 'card')) {
            $normalizedMethod = 'stripe';
        } else {
            $normalizedMethod = 'cash';
        }

        $reference = $request->input('reference') ?: ($normalizedMethod === 'cash' ? 'Manual Cash Recorded in CRM' : 'Manual Payment Recorded');

        // Delegate to PaymentController master settlement
        $paymentCtrl = new PaymentController();
        $paymentCtrl->settleInvoicePayment($invoice, $normalizedMethod, $amount, $reference);

        return response()->json([
            'message' => 'Payment recorded successfully and synced to General Ledger.',
            'invoice' => $this->present($invoice->refresh()),
        ]);
    }

    public function simulatePay(Request $request, string $id)
    {
        return redirect("/payment/checkout?invoice_id={$id}");
    }

    /**
     * Admin Endpoint to verify a Bank Transfer that was marked as 'Pending Verification'.
     */
    public function verifyBankTransfer(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) return response()->json(['error' => 'Invoice not found'], 404);

        if ($invoice->status === 'Paid') {
            return response()->json(['error' => 'Invoice is already paid'], 400);
        }

        // Delegate to PaymentController master settlement
        $paymentCtrl = new PaymentController();
        $amount = (float) $invoice->total;
        $reference = $invoice->payment_reference ?: 'Admin Verified Bank Transfer';
        $paymentCtrl->settleInvoicePayment($invoice, 'bank_transfer', $amount, $reference);

        return response()->json([
            'message' => 'Bank transfer verified successfully and synced to General Ledger.',
            'invoice' => $this->present($invoice->refresh()),
        ]);
    }
}
