@extends('emails.layouts.master', ['categoryTitle' => 'AFTERCARE, REVIEWS & REFERRALS'])

@section('content')
<div class="category-badge">PRIVATE REFERRAL</div>
<div class="headline">Good moves are worth passing on.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, if someone in your circle is planning a move, you can introduce them to Next Gen through your private referral link.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">YOUR REFERRAL CODE</div>
        <div class="detail-value">{{ $lead->referral_code ?: ('NG-REF-' . strtoupper(substr(md5($lead->id), 0, 6))) }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">CURRENT OFFER</div>
        <div class="detail-value">{{ $lead->referral_offer ?: '£100 Gift Card or 10% Move Credit for Friend' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">ELIGIBILITY</div>
        <div class="detail-value">Subject to campaign terms</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">SHARE MY REFERRAL LINK</a>
</div>

<div class="salutation" style="margin-top: 16px;">
    We will treat the referred customer with the same privacy and care we gave your move.
</div>

<div class="note-box">
    <p class="note-text">Referral marketing must use the correct consent/soft-opt-in basis and include an easy opt-out where required.</p>
</div>
@endsection
