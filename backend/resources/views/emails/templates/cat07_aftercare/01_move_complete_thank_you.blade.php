@extends('emails.layouts.master', ['categoryTitle' => 'AFTERCARE, REVIEWS & REFERRALS'])

@section('content')
<div class="category-badge">MOVE COMPLETE</div>
<div class="headline">The move is finished. The care does not stop here.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, thank you for trusting Next Gen Relocation with your move. We know a successful relocation is more than transport; it is the handover from one chapter to the next.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">COMPLETION REFERENCE</div>
        <div class="detail-value">{{ $lead->id }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">COMPLETED</div>
        <div class="detail-value">{{ $lead->completion_date ?: ($lead->move_date ? $lead->move_date->format('d M Y') : 'Today') }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">SUPPORT CONTACT</div>
        <div class="detail-value">{{ $lead->coordinator_name ?: 'Next Gen Aftercare Manager' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">IF ANYTHING NEEDS ATTENTION</div>
        <div class="detail-value">Use the support route below</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">OPEN AFTERCARE SUPPORT</a>
</div>

<div class="salutation" style="margin-top: 16px;">
    We hope the new space starts to feel like home quickly.
</div>

<div class="note-box">
    <p class="note-text">If you need to report an issue, tell us before we send review reminders so the matter can be handled properly.</p>
</div>
@endsection
