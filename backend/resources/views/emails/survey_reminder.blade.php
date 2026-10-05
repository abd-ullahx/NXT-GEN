@extends('emails.layouts.master', ['categoryTitle' => 'SURVEY REMINDER'])

@section('content')
<div class="category-badge">SURVEY REMINDER</div>
<div class="headline">Let's Get Your Pre-Move Survey Scheduled</div>

<div class="salutation">
    Dear <strong>{{ $lead->name ?: 'Valued Client' }}</strong>,
    we are ready to finalize your move plan! Please select a convenient date and survey method so our team can prepare your crew and vehicle allocations.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">ENQUIRY REF</div>
        <div class="detail-value">{{ $lead->id }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">SURVEY OPTIONS</div>
        <div class="detail-value">Physical, Live Video Call, or Video Upload</div>
    </div>
</div>

<div style="text-align: center; margin: 32px 0;">
    <a href="{{ $bookLink }}" class="cta-button">📅 BOOK MY PRE-MOVE SURVEY</a>
</div>

<div class="salutation" style="margin-top: 28px;">
    Warm regards,<br>
    <strong style="color: #FFFFFF;">Next Gen Relocation Team</strong>
</div>
@endsection
