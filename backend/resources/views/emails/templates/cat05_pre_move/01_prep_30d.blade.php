@extends('emails.layouts.master', ['categoryTitle' => 'PRE-MOVE PREPARATION'])

@section('content')
<div class="category-badge">30 DAYS TO GO</div>
<div class="headline">The best moves feel organised before the crew arrives.</div>
<div class="salutation">
    Dear {{ $lead->name ?: 'Valued Client' }}, with roughly one month to go, now is the ideal time to reduce friction: confirm dates, identify what will not move, gather important documents and decide where professional packing adds value.
</div>

<div class="details-card">
    <div style="color: #D1D5DB; font-size: 13px; line-height: 1.8;">
        &bull; Confirm the booking date and any flexibility<br>
        &bull; Review storage, disposal or donation needs<br>
        &bull; Identify valuables, artwork, pianos, safes or oversized items<br>
        &bull; Check parking/permit requirements at both addresses<br>
        &bull; Keep passports, medication, keys and essential documents separate
    </div>
</div>

<div style="text-align: center;">
    <a href="{{ $publicUrl ?? '#' }}" class="cta-button">OPEN MY PREPARATION DASHBOARD</a>
</div>

<div class="note-box">
    <p class="note-text">For international moves, customs and prohibited/restricted items vary by destination; follow destination-specific guidance from your relocation specialist.</p>
</div>
@endsection
