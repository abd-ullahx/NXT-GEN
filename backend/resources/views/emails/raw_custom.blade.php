@extends('emails.layouts.master', ['categoryTitle' => 'CLIENT COMMUNICATION'])

@section('content')
<div class="salutation" style="margin-bottom: 24px;">
    {!! $bodyContent !!}
</div>

<div class="salutation" style="margin-top: 28px;">
    Warm regards,<br>
    <strong style="color: #FFFFFF;">Next Gen Relocation Team</strong>
</div>
@endsection
