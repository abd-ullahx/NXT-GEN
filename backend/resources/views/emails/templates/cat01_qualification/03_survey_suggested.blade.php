@extends('emails.layouts.master', ['categoryTitle' => 'ENQUIRY & EARLY QUALIFICATION'])

@section('content')
<div class="category-badge">RECOMMENDED NEXT STEP</div>
<div class="headline">Precision before moving day.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, your enquiry has enough detail for us to begin, but a short survey will let us design the move properly rather than rely on assumptions. Choose the format that suits you — in-app/video or an in-person visit.
</div>

<div class="details-card">
    <div style="color: #D1D5DB; font-size: 13px; line-height: 1.8;">
        &bull; Confirm inventory and special items<br>
        &bull; Review stairs, lifts, parking and carrying distances<br>
        &bull; Identify packing, dismantling, storage or specialist requirements<br>
        &bull; Create a cleaner, more reliable final quotation
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">CHOOSE YOUR SURVEY</a>
</div>

<div class="note-box">
    <p class="note-text">Your survey link is secure and can be changed or rescheduled if needed.</p>
</div>
@endsection
