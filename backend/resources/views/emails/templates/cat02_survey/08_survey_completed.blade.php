@extends('emails.layouts.master', ['categoryTitle' => 'SURVEY BOOKING & ATTENDANCE'])

@section('content')
<div class="category-badge">SURVEY COMPLETE</div>
<div class="headline">Thank you — we have what we need.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, your survey is complete and the information has been passed into our quotation workflow. We are now checking scope, access, crew/vehicle needs and any specialist notes before the proposal is released.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">TARGET QUOTATION TIME</div>
        <div class="detail-value">approximately 30–45 minutes</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">SURVEY REFERENCE</div>
        <div class="detail-value">{{ $lead->id }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">ANY URGENT CORRECTION</div>
        <div class="detail-value">reply to this email immediately</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">VIEW MOVE WORKSPACE</a>
</div>

<div class="note-box">
    <p class="note-text">The 30–45 minute target is an operational aim and may extend where complex pricing or additional checks are required.</p>
</div>
@endsection
