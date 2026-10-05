@extends('emails.layouts.master', ['categoryTitle' => 'ENQUIRY & EARLY QUALIFICATION'])

@section('content')
<div class="category-badge">PRIVATE CLIENT WELCOME</div>
<div class="headline">A calmer move starts with clarity.</div>
<div class="salutation">
    Dear <strong>{{ $lead->name ?: 'Valued Client' }}</strong>, thank you for considering Next Gen Relocation. Your enquiry has been received and your move is now in our care. We will keep communication precise, personal, and beautifully simple from the first conversation to final placement.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">ENQUIRY REFERENCE</div>
        <div class="detail-value font-mono" style="color: #C9A84C; font-weight: 700;">{{ $lead->id }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">CLIENT NAME</div>
        <div class="detail-value">{{ $lead->name ?: 'Valued Client' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">COLLECTION LOCATION</div>
        <div class="detail-value">{{ $lead->from_location ?: 'TBC' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">DESTINATION LOCATION</div>
        <div class="detail-value">{{ $lead->to_location ?: 'TBC' }}</div>
    </div>
    @if($lead->move_date)
    <div class="detail-row">
        <div class="detail-label">REQUESTED MOVE DATE</div>
        <div class="detail-value">{{ \Carbon\Carbon::parse($lead->move_date)->format('d M Y') }}</div>
    </div>
    @endif
    <div class="detail-row">
        <div class="detail-label">PROPERTY / MOVE TYPE</div>
        <div class="detail-value">{{ $lead->move_type ?: 'Luxury Relocation' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">ESTIMATED VALUE RANGE</div>
        <div class="detail-value" style="color: #C9A84C; font-weight: 700;">
            {{ $lead->indicative_quote_range ?: ('£' . number_format($lead->est_value ?: 1500, 2)) }}
        </div>
    </div>
</div>

<div style="background-color: #14151C; border: 1px solid rgba(201, 168, 76, 0.2); border-radius: 12px; padding: 20px; margin: 24px 0;">
    <div style="color: #C9A84C; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 8px;">SCOPE SUMMARY</div>
    <p style="color: #D1D5DB; font-size: 13px; margin: 0; line-height: 1.5;">
        {{ $lead->scope_summary ?: 'Full packing, white-glove transport, and property setup tailored to your specification.' }}
    </p>
</div>

<div class="salutation" style="margin-top: 28px;">
    Warm regards,<br>
    <strong style="color: #FFFFFF;">Next Gen Relocation Team</strong>
</div>

<div class="note-box">
    <p class="note-text">For your security, sensitive inventory and booking information are protected under enterprise-grade encryption.</p>
</div>
@endsection
