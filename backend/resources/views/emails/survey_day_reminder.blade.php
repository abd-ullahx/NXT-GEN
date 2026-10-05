@extends('emails.layouts.master', ['categoryTitle' => 'CONFIRMED SURVEY APPOINTMENT'])

@section('content')
<div class="category-badge">PRE-MOVE SURVEY REMINDER</div>
<div class="headline">Your Property Survey Appointment</div>

<div class="salutation">
    Dear <strong>{{ $lead->name ?: 'Valued Client' }}</strong>,
    {!! $urgencyNote !!} Please review your appointment details and surveyor assignment below:
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">SURVEY REFERENCE</div>
        <div class="detail-value font-mono" style="color: #C9A84C; font-weight: 700;">SRV-{{ $lead->id }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">SCHEDULED DATE</div>
        <div class="detail-value" style="color: #C9A84C; font-weight: 700;">{{ $date }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">TIME WINDOW</div>
        <div class="detail-value">{{ $time }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">SURVEY METHOD</div>
        <div class="detail-value" style="text-transform: capitalize;">{{ $surveyType }} Survey</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">ASSIGNED SURVEYOR</div>
        <div class="detail-value">{{ $lead->surveyor_name ?: 'Next Gen Field Inspector' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">COLLECTION LOCATION</div>
        <div class="detail-value">{{ $lead->from_location ?: 'TBC' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">DESTINATION LOCATION</div>
        <div class="detail-value">{{ $lead->to_location ?: 'TBC' }}</div>
    </div>
</div>

@if(!empty($lead->surveyor_report_notes))
<div style="background-color: #14151C; border: 1px solid rgba(201, 168, 76, 0.2); border-radius: 12px; padding: 20px; margin: 24px 0;">
    <div style="color: #C9A84C; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 8px;">SURVEYOR INSPECTION NOTES</div>
    <p style="color: #D1D5DB; font-size: 13px; margin: 0; line-height: 1.5;">
        {{ $lead->surveyor_report_notes }}
    </p>
</div>
@endif

@if(!empty($lead->surveyor_media) && is_array($lead->surveyor_media) && count($lead->surveyor_media) > 0)
<div style="margin: 24px 0;">
    <div style="color: #C9A84C; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 12px;">PROPERTY INSPECTION MEDIA</div>
    <div style="display: table; width: 100%;">
        @foreach($lead->surveyor_media as $mediaUrl)
            @if(preg_match('/\.(jpeg|jpg|gif|png|webp)$/i', $mediaUrl) || str_starts_with($mediaUrl, 'data:image'))
                <div style="display: inline-block; width: 48%; margin-right: 2%; margin-bottom: 10px; vertical-align: top;">
                    <img src="{{ $mediaUrl }}" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(201, 168, 76, 0.3);" alt="Property Media" />
                </div>
            @endif
        @endforeach
    </div>
</div>
@endif

<div class="note-box">
    <p class="note-text">If you need to reschedule or add property access notes for your surveyor, please reply directly to this email or contact your coordinator on 0121 721 8295.</p>
</div>

<div class="salutation" style="margin-top: 28px;">
    Warm regards,<br>
    <strong style="color: #FFFFFF;">Next Gen Relocation Team</strong>
</div>
@endsection
