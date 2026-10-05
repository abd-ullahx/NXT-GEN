@extends('emails.layouts.document', [
    'documentTitle' => 'QUOTATION',
    'documentNumber' => $quote->quote_number ?? $quote->id,
    'categoryTitle' => $isPostSurvey ? 'POST-SURVEY QUOTATION' : 'INDICATIVE QUOTATION'
])

@section('content')
<div style="margin-bottom: 24px; color: #d1d5db; font-size: 14px; line-height: 1.6;">
    Dear <strong>{{ $quote->client_name ?: 'Valued Client' }}</strong>,<br><br>
    @if($isPostSurvey)
        Thank you for completing your pre-move survey! Please find your <strong>final moving quotation</strong> detailed below. Click <strong>Select Plan</strong> to confirm your move schedule.
    @else
        Please find your indicative relocation quotation detailed below. Review your line items and click <strong>Approve Quotation</strong> so we can arrange your pre-move survey.
    @endif
</div>

<div class="grid-2">
    <div class="col-left">
        <div class="card" style="min-height: 140px;">
            <div class="card-title">QUOTE TO</div>
            <div style="color: #f3f4f6; font-size: 16px; font-weight: bold; margin-bottom: 12px;">{{ $quote->client_name ?: 'Valued Client' }}</div>
            <div class="info-row">
                <div class="info-value-left">{{ $quote->client_email }}</div>
            </div>
            <div class="info-row">
                <div class="info-value-left" style="margin-top: 8px; font-size: 11px; color: #9ca3af;">Collection:</div>
            </div>
            <div class="info-row">
                <div class="info-value-left">{{ $quote->from_location ?: 'TBC' }}</div>
            </div>
            <div class="info-row">
                <div class="info-value-left" style="margin-top: 8px; font-size: 11px; color: #9ca3af;">Destination:</div>
            </div>
            <div class="info-row">
                <div class="info-value-left">{{ $quote->to_location ?: 'TBC' }}</div>
            </div>
        </div>
    </div>
    <div class="col-right">
        <div class="card" style="min-height: 140px;">
            <div class="card-title">QUOTATION DETAILS</div>
            <div class="info-row">
                <div class="info-label">Quote Number</div>
                <div class="info-value">{{ $quote->quote_number ?: $quote->id }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Issue Date</div>
                <div class="info-value">{{ $quote->created_at ? \Carbon\Carbon::parse($quote->created_at)->format('d F Y') : \Carbon\Carbon::now()->format('d F Y') }}</div>
            </div>
            @if($quote->move_date)
            <div class="info-row">
                <div class="info-label">Move Date</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($quote->move_date)->format('d F Y') }}</div>
            </div>
            @endif
            <div class="info-row">
                <div class="info-label">Valid Until</div>
                <div class="info-value">{{ $quote->valid_until ? \Carbon\Carbon::parse($quote->valid_until)->format('d F Y') : '14 Days' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="items-card">
    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach((array) $quote->items as $it)
            <tr>
                <td>{{ $it['label'] ?? '' }}</td>
                <td>{{ $it['qty'] ?? 1 }}</td>
                <td class="right">£{{ number_format((float)($it['unit_price'] ?? ($it['amount'] ?? 0)), 2) }}</td>
                <td class="right">£{{ number_format((float)($it['amount'] ?? 0), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="grid-2">
    <div class="col-left">
        @if($quote->notes)
        <div class="card" style="min-height: 160px;">
            <div class="card-title">NOTES & REPORT</div>
            <div style="font-size: 12px; color: #9ca3af; white-space: pre-wrap; line-height: 1.5;">{{ $quote->notes }}</div>
        </div>
        @else
        <div class="card" style="min-height: 160px; border-color: transparent; background-color: transparent;"></div>
        @endif
    </div>
    <div class="col-right">
        <div class="summary-box" style="min-height: 160px;">
            <div class="summary-row">
                <div class="summary-label">SUBTOTAL EXCL. VAT</div>
                <div class="summary-value">£{{ number_format((float)$quote->subtotal, 2) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-label">VAT @ 20%</div>
                <div class="summary-value">£{{ number_format((float)$quote->tax, 2) }}</div>
            </div>
            <div class="grand-total-box">
                <div class="grand-total-label">{{ empty($quote->packages) ? 'TOTAL INCLUDING VAT' : 'INDICATIVE TOTAL' }}</div>
                <div class="grand-total-value">£{{ number_format((float)$quote->total, 2) }}</div>
            </div>
            @if(!empty($quote->packages))
            <div class="summary-note">This is an indicative baseline total.</div>
            @endif
        </div>
    </div>
</div>

@php
    $displayPackages = !empty($quote->packages) && is_array($quote->packages) ? $quote->packages : null;
    if ($isPostSurvey && empty($displayPackages)) {
        $base = (float)($quote->subtotal ?: ($quote->total / 1.2));
        $bSub = round($base * 0.85, 2); $bTot = round($bSub * 1.20, 2);
        $sSub = round($base * 1.15, 2); $sTot = round($sSub * 1.20, 2);
        $pSub = round($base * 1.50, 2); $pTot = round($pSub * 1.20, 2);
        $displayPackages = [
            ['name' => 'Basic', 'tagline' => 'Self-Packing & Standard Removal', 'total' => $bTot, 'deposit_20' => round($bTot * 0.20, 2)],
            ['name' => 'Standard', 'tagline' => 'Full Service & Dismantling (Recommended)', 'total' => $sTot, 'deposit_20' => round($sTot * 0.20, 2)],
            ['name' => 'Premium', 'tagline' => 'White-Glove VIP Full Relocation', 'total' => $pTot, 'deposit_20' => round($pTot * 0.20, 2)],
        ];
    }
@endphp

@if(!empty($displayPackages) && is_array($displayPackages))
<div style="margin: 36px 0 24px 0;">
    <div style="color: #C9A84C; font-size: 14px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 8px; text-align: center;">SELECT YOUR RELOCATION PLAN</div>
    <p style="text-align: center; color: #9ca3af; font-size: 13px; margin-bottom: 24px;">Choose one of the 3 plans below to confirm your move schedule and proceed to the 20% advance deposit payment.</p>
    
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 24px;">
        @foreach($displayPackages as $pkg)
        @php
            $isRec = strcasecmp($pkg['name'] ?? '', 'Standard') === 0;
            $pkgTotal = number_format((float)($pkg['total'] ?? 0), 2);
            $pkgDeposit = number_format((float)($pkg['deposit_20'] ?? (($pkg['total'] ?? 0) * 0.20)), 2);
            $features = is_array($pkg['features'] ?? null) ? $pkg['features'] : [];
        @endphp
        <tr>
            <td style="padding-bottom: 20px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #141720; border: {{ $isRec ? '2px solid #C9A84C' : '1px solid rgba(201, 168, 76, 0.3)' }}; border-radius: 12px; padding: 24px; box-sizing: border-box;">
                    <tr>
                        <td style="text-align: center; padding-bottom: 16px;">
                            @if($isRec)
                                <div style="display: inline-block; background: #C9A84C; color: #0b0d12; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; padding: 4px 14px; border-radius: 20px; margin-bottom: 10px;">MOST POPULAR</div>
                            @endif
                            <div style="font-size: 22px; font-weight: bold; color: #FFFFFF; margin-bottom: 4px;">{{ $pkg['name'] ?? 'Plan' }} Plan</div>
                            <div style="font-size: 13px; color: #9ca3af;">{{ $pkg['tagline'] ?? '' }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px; background: rgba(201,168,76,0.08); border: 1px dashed rgba(201,168,76,0.3); border-radius: 10px; text-align: center;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="50%" style="text-align: center; border-right: 1px solid rgba(201,168,76,0.2); padding-right: 10px;">
                                        <div style="font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px;">TOTAL PLAN COST</div>
                                        <div style="font-size: 22px; font-weight: 800; color: #C9A84C; margin-top: 4px;">£{{ $pkgTotal }}</div>
                                    </td>
                                    <td width="50%" style="text-align: center; padding-left: 10px;">
                                        <div style="font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px;">20% DEPOSIT TO BOOK</div>
                                        <div style="font-size: 22px; font-weight: 800; color: #FFFFFF; margin-top: 4px;">£{{ $pkgDeposit }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @if(!empty($features))
                    <tr>
                        <td style="padding: 16px 0;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                @foreach($features as $f)
                                <tr>
                                    <td width="20" style="vertical-align: top; color: #C9A84C; font-size: 14px; padding-bottom: 6px;">✓</td>
                                    <td style="font-size: 13px; color: #d1d5db; padding-bottom: 6px;">{{ $f }}</td>
                                </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="text-align: center; padding-top: 12px;">
                            <a href="{{ $approveLink }}&package={{ urlencode($pkg['name'] ?? '') }}" 
                               style="display: block; width: 100%; box-sizing: border-box; background: {{ $isRec ? 'linear-gradient(135deg, #E2BA6E 0%, #9E7D3B 100%)' : '#C9A84C' }}; color: #0b0d12 !important; text-decoration: none; font-size: 13px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; padding: 14px 20px; border-radius: 8px; text-align: center;">
                                SELECT {{ strtoupper($pkg['name'] ?? '') }} & PAY £{{ $pkgDeposit }} DEPOSIT
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        @endforeach
    </table>
</div>
@else
<div style="text-align: center; margin: 40px 0;">
    <a href="{{ $approveLink }}" class="cta-button">✓ APPROVE QUOTATION</a>
</div>
<p style="font-size: 11px; color: #9CA3AF; text-align: center; margin-top: -20px;">
    Or copy and paste this link:<br/>
    <a href="{{ $approveLink }}" style="color: #C9A84C; word-break: break-all;">{{ $approveLink }}</a>
</p>
@endif
@endsection
