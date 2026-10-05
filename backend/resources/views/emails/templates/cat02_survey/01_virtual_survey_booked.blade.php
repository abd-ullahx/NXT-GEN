@extends('emails.layouts.master', ['categoryTitle' => 'SURVEY BOOKING & ATTENDANCE'])

@section('content')
<div class="category-badge">SURVEY CONFIRMED</div>
<div class="headline">Your virtual survey is in the diary.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, your video/in-app survey is confirmed. We will guide you room by room, note access details and identify anything requiring special planning.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">DATE</div>
        <div class="detail-value">{{ $lead->survey_requested_date ?: 'Confirmed' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">TIME</div>
        <div class="detail-value">{{ $lead->survey_requested_time_range ?: 'Scheduled Time' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">METHOD</div>
        <div class="detail-value">Video / In-App Video Call</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">SURVEYOR</div>
        <div class="detail-value">{{ $lead->surveyor_name ?: 'Assigned Relocation Specialist' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">REFERENCE</div>
        <div class="detail-value">{{ $lead->id }}</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">OPEN SURVEY APPOINTMENT</a>
</div>

<div class="note-box">
    <p class="note-text">Avoid sharing passports, payment-card details or unrelated personal documents on video.</p>
</div>
@endsection
