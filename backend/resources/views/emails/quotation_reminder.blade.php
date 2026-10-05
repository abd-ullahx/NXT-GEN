@extends('emails.layouts.master', ['categoryTitle' => 'PROPOSAL FOLLOW-UP'])

@section('content')
<div class="category-badge">QUOTATION REMINDER</div>
<div class="headline">Just a Friendly Follow-Up on Your Move</div>

<div class="salutation">
    Dear <strong>{{ $lead->name ?: 'Valued Client' }}</strong>,
    we noticed you haven't yet reviewed or approved your moving quotation. It only takes a single click to approve your proposal so our team can finalize your move schedule and reserve your date.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">ENQUIRY REFERENCE</div>
        <div class="detail-value">{{ $lead->id }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">COLLECTION</div>
        <div class="detail-value">{{ $lead->from_location ?: 'TBC' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">DESTINATION</div>
        <div class="detail-value">{{ $lead->to_location ?: 'TBC' }}</div>
    </div>
</div>

<div style="text-align: center; margin: 32px 0;">
    <a href="{{ $approveLink }}" class="cta-button">✓ REVIEW & APPROVE QUOTATION</a>
</div>

<div class="salutation" style="margin-top: 28px;">
    Warm regards,<br>
    <strong style="color: #FFFFFF;">Next Gen Relocation Team</strong>
</div>
@endsection
