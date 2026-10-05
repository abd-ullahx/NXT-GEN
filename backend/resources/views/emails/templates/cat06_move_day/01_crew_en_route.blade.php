@extends('emails.layouts.master', ['categoryTitle' => 'MOVE DAY LIVE EXECUTION'])

@section('content')
<div class="category-badge">CREW EN ROUTE</div>
<div class="headline">Our vehicle & crew are on their way to you.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, good morning! Your Next Gen Relocation vehicle and specialist crew are currently en route to your collection address.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">ESTIMATED ARRIVAL</div>
        <div class="detail-value">{{ $lead->arrival_window ?: 'Within 20–30 Minutes' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">COLLECTION ADDRESS</div>
        <div class="detail-value">{{ $lead->from_location }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">CREW LEAD</div>
        <div class="detail-value">{{ $lead->job_lead_name ?: 'Senior Crew Leader' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">LIVE TRACKING</div>
        <div class="detail-value">Active via Portal</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">TRACK CREW LOCATION</a>
</div>

<div class="note-box">
    <p class="note-text">Please ensure parking/access pathways at collection are kept clear for vehicle placement.</p>
</div>
@endsection
