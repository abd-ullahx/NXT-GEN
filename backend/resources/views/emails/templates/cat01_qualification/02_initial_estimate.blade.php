@extends('emails.layouts.master', ['categoryTitle' => 'ENQUIRY & EARLY QUALIFICATION'])

@section('content')
<div class="category-badge">INITIAL ESTIMATE</div>
<div class="headline">An early view of your move investment.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, based on the details currently available, we have prepared an indicative estimate to help you plan with confidence. This is intentionally transparent: it is not the final quotation and may change once access, inventory, specialist items and service choices are confirmed.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">INDICATIVE RANGE</div>
        <div class="detail-value">{{ $lead->indicative_quote_range ?: ('£' . number_format($lead->est_value ?: 1500, 2)) }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">MOVE DATE</div>
        <div class="detail-value">{{ $lead->move_date ? $lead->move_date->format('d M Y') : 'Pending' }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">CURRENT SCOPE</div>
        <div class="detail-value">{{ $lead->scope_summary ?: ($lead->move_type ?: 'Standard Relocation') }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">TO FINALISE ACCURATELY</div>
        <div class="detail-value">{{ $lead->missing_information_summary ?: 'Complete quick inventory & property access survey' }}</div>
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">REFINE MY QUOTATION</a>
</div>

<div class="note-box">
    <p class="note-text">Indicative estimate only; subject to survey/inventory confirmation, availability, terms and any stated assumptions.</p>
</div>
@endsection
