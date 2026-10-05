@extends('emails.layouts.master', ['categoryTitle' => 'ENQUIRY & EARLY QUALIFICATION'])

@section('content')
<div class="category-badge">PRIVATE CLIENT WELCOME</div>
<div class="headline">A calmer move starts with clarity.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, thank you for considering Next Gen Relocation. Your enquiry has been received and your move is now in our care. We will keep communication precise, personal and beautifully simple from the first conversation to final placement.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">REFERENCE</div>
        <div class="detail-value">{{ $lead->id }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">REQUESTED MOVE DATE</div>
        <div class="detail-value">{{ $lead->move_date ? $lead->move_date->format('d M Y') : 'Pending' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">COLLECTION</div>
        <div class="detail-value">{{ $lead->from_location ?: 'TBC' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">DESTINATION</div>
        <div class="detail-value">{{ $lead->to_location ?: 'TBC' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">NEXT STEP</div>
        <div class="detail-value">A relocation specialist will contact you</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">REVIEW YOUR MOVE DETAILS</a>
</div>

<div class="salutation" style="margin-top: 24px;">
    Warm regards,<br>
    <strong style="color: #FFFFFF;">{{ $lead->coordinator_name ?: 'Private Client Relocation Team' }}</strong>
</div>

<div class="note-box">
    <p class="note-text">For your security, sensitive inventory, identity and payment information should only be shared through the secure portal or with your named coordinator.</p>
</div>
@endsection
