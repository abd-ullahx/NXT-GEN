@extends('emails.layouts.master', ['categoryTitle' => 'SURVEY APPROVED & SCHEDULED'])

@section('content')
<div class="category-badge">SURVEY CONFIRMATION</div>
<div class="headline">Your Pre-Move Survey Has Been Approved.</div>
<div class="salutation">
    Dear <strong>{{ $lead->name ?: 'Valued Client' }}</strong>, your pre-move property survey has been officially approved and scheduled by our management team. We look forward to assessing your inventory and ensuring every detail of your upcoming move is perfectly planned.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">SURVEY REFERENCE</div>
        <div class="detail-value font-mono" style="color: #C9A84C; font-weight: 700;">SRV-{{ $lead->id }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">SURVEY DATE</div>
        <div class="detail-value" style="color: #C9A84C; font-weight: 700;">{{ $lead->survey_requested_date }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">CONFIRMED TIME</div>
        <div class="detail-value" style="color: #C9A84C; font-weight: 700;">{{ $lead->survey_requested_time_range }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">SURVEY MODE</div>
        <div class="detail-value" style="text-transform: capitalize;">{{ $lead->survey_type ?: 'Physical' }} Survey</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">PROPERTY LOCATION</div>
        <div class="detail-value">{{ $lead->from_location ?: 'TBC' }}</div>
    </div>
</div>

@if($lead->app_user_password)
<div style="background-color: #14151C; border: 1px solid rgba(201, 168, 76, 0.2); border-radius: 12px; padding: 20px; margin: 24px 0;">
    <div style="color: #C9A84C; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 8px;">MOBILE APP CREDENTIALS</div>
    <p style="color: #D1D5DB; font-size: 13px; margin: 0 0 10px 0; line-height: 1.5;">
        You can track your survey status, video calls, and inventory photos on our Mobile App:
    </p>
    <div style="font-family: monospace; font-size: 13px; color: #FFFFFF;">
        <strong>Email:</strong> {{ $lead->email }}<br>
        <strong>Temp Password:</strong> <span style="color: #C9A84C;">{{ $lead->app_user_password }}</span>
    </div>
</div>
@endif

<div class="salutation" style="margin-top: 28px;">
    Warm regards,<br>
    <strong style="color: #FFFFFF;">Next Gen Relocation Survey Team</strong>
</div>
@endsection
