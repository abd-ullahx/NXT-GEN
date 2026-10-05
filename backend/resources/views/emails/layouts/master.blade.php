<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? 'Next Gen Relocation' }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #0B0C10; color: #E5E7EB; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #0B0C10; padding: 40px 0; }
        .main { max-width: 640px; margin: 0 auto; background-color: #0F1015; border: 1px solid rgba(201, 168, 76, 0.35); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.6); }
        .header { padding: 32px 36px 20px 36px; border-bottom: 1px solid rgba(201, 168, 76, 0.15); background-color: #121319; }
        .brand-title { color: #C9A84C; font-size: 11px; letter-spacing: 3px; font-weight: 700; text-transform: uppercase; margin: 0 0 4px 0; }
        .brand-subtitle { color: #9CA3AF; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; margin: 0; }
        .body-content { padding: 36px; }
        .category-badge { color: #C9A84C; font-size: 10px; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase; margin-bottom: 8px; }
        .headline { color: #FFFFFF; font-family: 'Georgia', serif; font-size: 28px; font-weight: 600; line-height: 1.3; margin: 0 0 16px 0; }
        .salutation { color: #D1D5DB; font-size: 15px; line-height: 1.6; margin-bottom: 24px; }
        .details-card { background-color: #16171E; border: 1px solid rgba(201, 168, 76, 0.25); border-radius: 12px; padding: 20px 24px; margin: 28px 0; }
        .detail-row { display: table; width: 100%; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { display: table-cell; color: #9CA3AF; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; width: 40%; vertical-align: top; }
        .detail-value { display: table-cell; color: #F3F4F6; font-size: 13px; font-weight: 600; text-align: right; width: 60%; vertical-align: top; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, #E2BA6E 0%, #9E7D3B 100%); color: #0B0C10 !important; font-size: 13px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; text-decoration: none; padding: 14px 28px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .note-box { background-color: #14151B; border-left: 3px solid #C9A84C; padding: 14px 18px; margin-top: 32px; border-radius: 0 8px 8px 0; }
        .note-text { color: #9CA3AF; font-size: 12px; line-height: 1.5; margin: 0; }
        .footer { padding: 24px 36px; border-top: 1px solid rgba(201, 168, 76, 0.15); background-color: #0D0E12; font-size: 11px; color: #6B7280; text-align: center; }
        .footer-tagline { color: #C9A84C; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main">
            <!-- Header -->
            <div class="header">
                <div class="brand-title">NEXT GEN RELOCATION LTD</div>
                <div class="brand-subtitle">{{ $categoryTitle ?? 'PREMIUM RELOCATION SERVICES' }}</div>
            </div>

            <!-- Body -->
            <div class="body-content">
                @yield('content')
            </div>

            <!-- Footer -->
            <div class="footer">
                <div>0121 721 8295 &bull; info@nextgenrelocation.co.uk &bull; nextgenrelocation.co.uk</div>
                <div class="footer-tagline">NEXT GEN, NEXT HOME, NEXT CHAPTER</div>
            </div>
        </div>
    </div>
    @if(!empty($trackingUrl))
        <img src="{{ $trackingUrl }}" width="1" height="1" style="display:none;" alt="" />
    @endif
</body>
</html>
