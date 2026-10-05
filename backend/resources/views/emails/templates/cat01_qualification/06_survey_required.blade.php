@extends('emails.layouts.master', ['categoryTitle' => 'ENQUIRY & EARLY QUALIFICATION'])

@section('content')
<div class="category-badge">ACTION REQUIRED</div>
<div class="headline">We need the full picture before we price.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, to issue a dependable final quotation, we need to verify the inventory, property access and any special handling requirements. This protects you from preventable surprises and lets our operations team plan the correct crew, vehicle and time allowance.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">REQUIRED BY</div>
        <div class="detail-value">{{ $lead->survey_deadline ?: 'Within 48 hours' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">PREFERRED FORMAT</div>
        <div class="detail-value">{{ $lead->recommended_survey_type ?: 'Video / In-App Survey' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">ITEMS REQUIRING ATTENTION</div>
        <div class="detail-value">{{ $lead->special_item_prompt ?: 'Artwork, antiques, pianos or high-value items' }}</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">SCHEDULE THE REQUIRED SURVEY</a>
</div>

<div class="note-box">
    <p class="note-text">If your move is urgent, call 0121 721 8295 and quote {{ $lead->id }}.</p>
</div>
@endsection
