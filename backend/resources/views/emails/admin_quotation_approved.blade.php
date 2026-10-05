@extends('emails.layouts.master', ['categoryTitle' => 'QUOTATION APPROVED BY CLIENT'])

@section('content')
<div class="category-badge">QUOTATION APPROVED</div>
<div class="headline">Client Approved Quotation {{ $quote->quote_number }}.</div>
<div class="salutation">
    Client <strong>{{ $quote->client_name }}</strong> has reviewed and approved the quotation <strong>{{ $quote->quote_number }}</strong>. The status has been updated to approved.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">QUOTE NUMBER</div>
        <div class="detail-value font-mono" style="color: #C9A84C; font-weight: 700;">{{ $quote->quote_number }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">CLIENT NAME</div>
        <div class="detail-value">{{ $quote->client_name }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">TOTAL AMOUNT</div>
        <div class="detail-value" style="color: #C9A84C; font-weight: 700;">£{{ number_format((float)$quote->total, 2) }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">MOVE DATE</div>
        <div class="detail-value">{{ $quote->move_date ? \Carbon\Carbon::parse($quote->move_date)->format('d M Y') : 'TBC' }}</div>
    </div>
</div>

<div class="salutation" style="margin-top: 28px;">
    Next Gen Relocation System Notification
</div>
@endsection
