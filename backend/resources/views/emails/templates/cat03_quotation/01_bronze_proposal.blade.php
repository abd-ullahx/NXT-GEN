@extends('emails.layouts.master', ['categoryTitle' => 'QUOTATION & CONVERSION'])

@section('content')
<div class="category-badge">BRONZE SIGNATURE PROPOSAL</div>
<div class="headline">A considered move plan, now priced.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, thank you for the time you gave us during the survey. We have translated the detail into a tailored Bronze Signature proposal: clear scope, transparent assumptions and a secure route to accept.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">QUOTATION</div>
        <div class="detail-value">£{{ number_format($lead->est_value ?: 1850, 2) }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">MOVE DATE</div>
        <div class="detail-value">{{ $lead->move_date ? $lead->move_date->format('d M Y') : 'Confirmed' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">INCLUDED SERVICES</div>
        <div class="detail-value">{{ $lead->quoted_services ?: 'Full Packing, Transport, Furniture Placement & Debris Removal' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">VALIDITY</div>
        <div class="detail-value">{{ $lead->quote_valid_until ?: '14 Days from Issue' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">DEPOSIT ON ACCEPTANCE</div>
        <div class="detail-value">{{ $lead->deposit_amount_or_percent ?: '25% or £250' }}</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">VIEW BRONZE PROPOSAL</a>
</div>

<div class="salutation" style="margin-top: 16px;">
    If you would like to adjust packing, dismantling, storage or specialist handling, your coordinator can revise the scope without losing the audit trail.
</div>

<div class="note-box">
    <p class="note-text">Only services listed in the quotation are included. The signed quotation and terms take precedence over this email summary.</p>
</div>
@endsection
