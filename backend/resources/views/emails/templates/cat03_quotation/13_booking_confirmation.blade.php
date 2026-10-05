@extends('emails.layouts.master', ['categoryTitle' => 'QUOTATION & CONVERSION'])

@section('content')
<div class="category-badge">BOOKING CONFIRMED</div>
<div class="headline">Your move is officially in the diary.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, your booking is confirmed. From here, communication becomes operational: preparation reminders, final checks, tracking and move-day status will arrive at the right time without asking you to remember everything.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">BOOKING REFERENCE</div>
        <div class="detail-value">{{ $lead->id }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">MOVE DATE</div>
        <div class="detail-value">{{ $lead->move_date ? $lead->move_date->format('d M Y') : 'Confirmed' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">COLLECTION</div>
        <div class="detail-value">{{ $lead->from_location }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">DESTINATION</div>
        <div class="detail-value">{{ $lead->to_location }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">COORDINATOR</div>
        <div class="detail-value">{{ $lead->coordinator_name ?: 'Next Gen Operations Team' }}</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">OPEN MY MOVE DASHBOARD</a>
</div>

<div class="note-box">
    <p class="note-text">Please tell us promptly about material inventory, access or date changes; they can affect resources, timings and price.</p>
</div>
@endsection
