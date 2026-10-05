@extends('emails.layouts.master', ['categoryTitle' => 'BOOKING & DISPATCH CONFIRMATION'])

@section('content')
<div class="category-badge">DEPOSIT CONFIRMED</div>
<div class="headline">Deposit received — your date is secured.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, we have successfully processed your deposit payment of {{ $lead->deposit_amount_or_percent ?: '25%' }}. Your relocation date is now fully locked in our master operational schedule.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">BOOKING REFERENCE</div>
        <div class="detail-value">{{ $lead->id }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">CONFIRMED MOVE DATE</div>
        <div class="detail-value">{{ $lead->move_date ? $lead->move_date->format('d M Y') : 'Confirmed' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">PAYMENT STATUS</div>
        <div class="detail-value">Deposit Received (Confirmed)</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">COORDINATOR</div>
        <div class="detail-value">{{ $lead->coordinator_name ?: 'Next Gen Operations Team' }}</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">VIEW BOOKING CONFIRMATION</a>
</div>

<div class="note-box">
    <p class="note-text">Our dispatch manager will assign your dedicated crew and vehicle. You will receive pre-move preparation guidance as your move date approaches.</p>
</div>
@endsection
