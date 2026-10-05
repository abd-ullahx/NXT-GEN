@extends('emails.layouts.master', ['categoryTitle' => 'PRE-MOVE SURVEY SCHEDULING'])

@section('content')
<div class="category-badge">PRE-MOVE SURVEY REQUEST</div>
<div class="headline">Let's Schedule Your Pre-Move Survey</div>

<div class="salutation">
    Dear <strong>{{ $lead->name ?: 'Valued Client' }}</strong>,
    thank you for approving your quotation! To ensure zero surprises on move day, the next step is scheduling a quick pre-move survey. Please select your preferred date, time, and survey option:
</div>

<div class="details-card">
    <div class="detail-row">
        <div class="detail-label">OPTION 1: PHYSICAL SURVEY</div>
        <div class="detail-value">Surveyor visits property</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">OPTION 2: LIVE VIDEO CALL</div>
        <div class="detail-value">Virtual video walkthrough</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">OPTION 3: VIDEO UPLOAD</div>
        <div class="detail-value">Upload video recording</div>
    </div>
</div>

<div style="text-align: center; margin: 32px 0;">
    <a href="{{ $bookLink }}" class="cta-button">📅 BOOK MY PRE-MOVE SURVEY</a>
</div>

<p style="font-size: 12px; color: #9CA3AF; text-align: center;">
    Or copy and paste this link into your browser:<br/>
    <a href="{{ $bookLink }}" style="color: #C9A84C; word-break: break-all;">{{ $bookLink }}</a>
</p>

<div class="salutation" style="margin-top: 28px;">
    Warm regards,<br>
    <strong style="color: #FFFFFF;">Next Gen Relocation Team</strong>
</div>
@endsection
