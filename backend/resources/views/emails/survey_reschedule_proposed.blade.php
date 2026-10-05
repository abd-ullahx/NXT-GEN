@extends('emails.layouts.master', ['categoryTitle' => 'PROPOSED SURVEY SCHEDULE'])

@section('content')
<div class="category-badge">ACTION REQUIRED — RESCHEDULE PROPOSAL</div>
<div class="headline">Proposed New Time For Your Pre-Move Survey.</div>
<div class="salutation">
    Dear <strong>{{ $lead->name ?: 'Valued Client' }}</strong>, our team has reviewed your survey request. To ensure our senior surveyor is available, we have proposed a new survey schedule for your review:
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">PREVIOUSLY REQUESTED</div>
        <div class="detail-value" style="color: #9CA3AF;">{{ $lead->survey_requested_date }} at {{ $lead->survey_requested_time_range }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">PROPOSED NEW DATE</div>
        <div class="detail-value" style="color: #C9A84C; font-weight: 700; font-size: 15px;">{{ $lead->reschedule_proposed_date }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">PROPOSED EXACT TIME</div>
        <div class="detail-value" style="color: #C9A84C; font-weight: 700; font-size: 15px;">{{ $lead->reschedule_proposed_time }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">SURVEY MODE</div>
        <div class="detail-value" style="text-transform: capitalize;">{{ $lead->survey_type ?: 'Physical' }} Survey</div>
    </div>
</div>

<div style="text-align: center; margin: 32px 0;">
    <a href="{{ $approveLink }}" style="background: linear-gradient(135deg, #D4AF37 0%, #AA7C11 100%); color: #000000; font-weight: 700; font-size: 14px; text-decoration: none; padding: 14px 28px; border-radius: 8px; display: inline-block; box-shadow: 0 4px 12px rgba(212,175,55,0.3);">
        ✓ Approve Proposed Survey Schedule
    </a>
</div>

<div class="salutation" style="margin-top: 28px;">
    Warm regards,<br>
    <strong style="color: #FFFFFF;">Next Gen Relocation Survey Team</strong>
</div>
@endsection
