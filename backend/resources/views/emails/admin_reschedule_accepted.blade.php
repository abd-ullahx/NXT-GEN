@extends('emails.layouts.master', ['categoryTitle' => 'CLIENT CONFIRMED RESCHEDULE'])

@section('content')
<div class="category-badge">CLIENT CONFIRMED NEW SCHEDULE</div>
<div class="headline">Client Approved Proposed Survey Time.</div>
<div class="salutation">
    Client <strong>{{ $lead->name }}</strong> has accepted the proposed survey schedule. The survey schedule is now confirmed by the client and ready for Admin approval.
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">CLIENT NAME</div>
        <div class="detail-value">{{ $lead->name }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">CONFIRMED DATE</div>
        <div class="detail-value" style="color: #C9A84C; font-weight: 700;">{{ $lead->survey_requested_date }}</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">CONFIRMED EXACT TIME</div>
        <div class="detail-value" style="color: #C9A84C; font-weight: 700;">{{ $lead->survey_requested_time_range }}</div>
    </div>
</div>

<div class="salutation" style="margin-top: 28px;">
    Next Gen Relocation System Notification
</div>
@endsection
