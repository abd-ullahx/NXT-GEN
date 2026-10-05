@extends('emails.layouts.document', [
    'documentTitle' => 'INVOICE',
    'documentNumber' => $invoice->invoice_number ?? $invoice->id,
    'categoryTitle' => $invoice->service_title ?? 'PREMIUM COMMERCIAL CLEARANCE'
])

@section('content')
<div style="margin-bottom: 24px; color: #d1d5db; font-size: 14px; line-height: 1.6;">
    Dear <strong>{{ $invoice->client_name ?: 'Valued Client' }}</strong>,<br><br>
    Please find attached/below your invoice for our services. Payment is due by <strong>{{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d F Y') : 'TBC' }}</strong>.
</div>

<div class="grid-2">
    <div class="col-left">
        <div class="card" style="min-height: 140px;">
            <div class="card-title">BILL TO</div>
            <div style="color: #f3f4f6; font-size: 16px; font-weight: bold; margin-bottom: 12px;">{{ $invoice->client_name ?: 'Valued Client' }}</div>
            <div class="info-row">
                <div class="info-value-left">{{ $invoice->client_email }}</div>
            </div>
        </div>
    </div>
    <div class="col-right">
        <div class="card" style="min-height: 140px;">
            <div class="card-title">INVOICE DETAILS</div>
            <div class="info-row">
                <div class="info-label">Invoice Number</div>
                <div class="info-value">{{ $invoice->invoice_number ?: $invoice->id }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Issue Date</div>
                <div class="info-value">{{ $invoice->issue_date ? \Carbon\Carbon::parse($invoice->issue_date)->format('d F Y') : \Carbon\Carbon::now()->format('d F Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Due Date</div>
                <div class="info-value">{{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d F Y') : 'TBC' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="items-card">
    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach((array) $invoice->items as $it)
            <tr>
                <td>{{ $it['description'] ?? ($it['label'] ?? '') }}</td>
                <td>{{ $it['qty'] ?? 1 }}</td>
                <td class="right">£{{ number_format((float)($it['unit_price'] ?? ($it['amount'] ?? 0)), 2) }}</td>
                <td class="right">£{{ number_format((float)($it['amount'] ?? 0), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="grid-2">
    <div class="col-left">
        <div class="card" style="min-height: 160px;">
            <div class="card-title" style="margin-bottom: 12px;">BANK DETAILS</div>
            <div class="info-row" style="margin-bottom: 6px;">
                <div class="info-label" style="width:30%;">Bank:</div>
                <div class="info-value" style="text-align:left; width:70%;">Barclays</div>
            </div>
            <div class="info-row" style="margin-bottom: 6px;">
                <div class="info-label" style="width:30%;">Account:</div>
                <div class="info-value" style="text-align:left; width:70%;">NEXT GEN RELOCATION LTD</div>
            </div>
            <div class="info-row" style="margin-bottom: 6px;">
                <div class="info-label" style="width:30%;">Sort Code:</div>
                <div class="info-value" style="text-align:left; width:70%;">20-03-84</div>
            </div>
            <div class="info-row" style="margin-bottom: 12px;">
                <div class="info-label" style="width:30%;">Account No:</div>
                <div class="info-value" style="text-align:left; width:70%;">93591263</div>
            </div>
            <div style="font-size: 11px; color: #9ca3af; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 12px;">
                Use invoice number <strong>{{ $invoice->invoice_number ?: $invoice->id }}</strong> as the payment reference.
            </div>
        </div>
    </div>
    <div class="col-right">
        <div class="summary-box" style="min-height: 160px;">
            <div class="summary-row">
                <div class="summary-label">SUBTOTAL EXCL. VAT</div>
                <div class="summary-value">£{{ number_format((float)$invoice->subtotal, 2) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-label">VAT @ 20%</div>
                <div class="summary-value">£{{ number_format((float)$invoice->tax, 2) }}</div>
            </div>
            <div class="grand-total-box">
                <div class="grand-total-label">TOTAL INCLUDING VAT</div>
                <div class="grand-total-value">£{{ number_format((float)$invoice->total, 2) }}</div>
            </div>
            <div class="summary-note">This invoice includes VAT</div>
        </div>
    </div>
</div>

<div style="text-align: center; margin: 40px 0;">
    <a href="{{ url('/api/invoice/'.$invoice->id.'/simulate-pay') }}" class="cta-button">✓ PAY INVOICE NOW</a>
</div>
@endsection
