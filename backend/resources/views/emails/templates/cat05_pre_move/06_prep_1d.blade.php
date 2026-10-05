@extends('emails.layouts.master', ['categoryTitle' => 'PRE-MOVE PREPARATION'])

@section('content')
<div class="category-badge">TOMORROW</div>
<div class="headline">Everything you need for the final evening.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, tomorrow is move day. Your job has reached final dispatch preparation. Keep tonight simple: protect your essentials, keep access clear and have keys/documents to hand.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">ARRIVAL WINDOW</div>
        <div class="detail-value">{{ $lead->arrival_window ?: '08:00 – 09:00 AM' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">JOB LEAD</div>
        <div class="detail-value">{{ $lead->job_lead_name ?: 'Senior Crew Lead' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">URGENT CONTACT</div>
        <div class="detail-value">0121 721 8295</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">TRACKING / DASHBOARD</div>
        <div class="detail-value">Available via Live Portal</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">PAYMENT STATUS</div>
        <div class="detail-value">{{ $lead->payment_status ?: 'Confirmed' }}</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">OPEN TOMORROW'S MOVE DETAILS</a>
</div>

<div class="salutation" style="margin-top: 16px;">
    We will send the next operational update when the crew is en route.
</div>

<div class="note-box">
    <p class="note-text">If a genuine access or key issue arises overnight, use the urgent route shown in your booking details.</p>
</div>
@endsection
