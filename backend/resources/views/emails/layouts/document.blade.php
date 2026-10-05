<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle ?? 'Document' }} | Next Gen Relocation</title>
    <style>
        body {
            margin: 0; padding: 0;
            background-color: #0b0c10;
            color: #d1d5db;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            background-color: #0b0c10;
            padding: 40px 0;
        }
        .main {
            max-width: 800px;
            margin: 0 auto;
            background-color: #101218;
            overflow: hidden;
            border-top: 1px solid #c9a84c;
        }
        
        .header {
            padding: 40px;
            display: table;
            width: 100%;
            box-sizing: border-box;
            border-bottom: 1px solid rgba(201, 168, 76, 0.2);
        }
        .header-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .header-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: right;
        }
        .logo-container {
            display: inline-block;
            vertical-align: top;
            margin-right: 20px;
        }
        .company-info {
            display: inline-block;
            vertical-align: top;
        }
        .company-name {
            color: #c9a84c;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 4px;
            letter-spacing: 1px;
        }
        .company-tagline {
            color: #9ca3af;
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .doc-title {
            color: #c9a84c;
            font-size: 28px;
            font-weight: bold;
            font-family: 'Georgia', serif;
            letter-spacing: 2px;
            margin: 0 0 8px 0;
            text-transform: uppercase;
        }
        .doc-number {
            color: #c9a84c;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .content {
            padding: 40px;
        }
        
        /* Grid System (Tables for email) */
        .grid-2 {
            width: 100%;
            display: table;
            table-layout: fixed;
            margin-bottom: 24px;
        }
        .col-left {
            display: table-cell;
            width: 48%;
            padding-right: 2%;
            vertical-align: top;
        }
        .col-right {
            display: table-cell;
            width: 48%;
            padding-left: 2%;
            vertical-align: top;
        }
        
        /* Cards */
        .card {
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 8px;
            padding: 24px;
            background-color: #141720;
            min-height: 140px;
        }
        .card-title {
            color: #c9a84c;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            font-size: 12px;
        }
        .info-row:last-child {
            margin-bottom: 0;
        }
        .info-label {
            display: table-cell;
            width: 40%;
            color: #9ca3af;
        }
        .info-value {
            display: table-cell;
            width: 60%;
            color: #f3f4f6;
            text-align: right;
        }
        .info-value-left {
            display: table-cell;
            width: 60%;
            color: #f3f4f6;
            text-align: left;
        }

        /* Items Table */
        .items-card {
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            background-color: #141720;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            color: #c9a84c;
            font-size: 11px;
            font-weight: bold;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .items-table th.right { text-align: right; }
        .items-table td {
            padding: 16px 0;
            color: #f3f4f6;
            font-size: 13px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            vertical-align: top;
        }
        .items-table td.right { text-align: right; }
        .items-table tr:last-child td { border-bottom: none; }
        
        .item-desc { margin-top: 6px; font-size: 11px; color: #9ca3af; }

        /* Summary Box */
        .summary-box {
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 8px;
            padding: 24px;
            background-color: #141720;
        }
        .summary-row {
            display: table;
            width: 100%;
            font-size: 12px;
            margin-bottom: 16px;
        }
        .summary-label { display: table-cell; color: #9ca3af; text-transform: uppercase; }
        .summary-value { display: table-cell; color: #f3f4f6; text-align: right; }
        
        .grand-total-box {
            border: 1px solid #c9a84c;
            border-radius: 6px;
            padding: 16px;
            text-align: center;
            margin-top: 20px;
        }
        .grand-total-label {
            color: #c9a84c;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .grand-total-value {
            color: #c9a84c;
            font-size: 28px;
            font-weight: bold;
        }
        .summary-note {
            text-align: center;
            color: #9ca3af;
            font-size: 10px;
            margin-top: 12px;
        }

        .bank-details {
            display: table;
            width: 100%;
            font-size: 12px;
            color: #9ca3af;
        }
        .bank-col { display: table-cell; }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: table;
            width: 100%;
            font-size: 10px;
            color: #9ca3af;
        }
        .footer-left { display: table-cell; }
        .footer-right { display: table-cell; text-align: right; }
        .footer-center { display: table-cell; text-align: center; color: #c9a84c; font-weight: bold; letter-spacing: 1px; }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #E2BA6E 0%, #9E7D3B 100%);
            color: #0B0C10 !important;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <div class="company-info">
                        <div class="company-name">NEXT GEN RELOCATION LTD</div>
                        <div class="company-tagline">{{ $categoryTitle ?? 'PREMIUM COMMERCIAL CLEARANCE' }}</div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="doc-title">{{ $documentTitle ?? 'INVOICE' }}</div>
                    <div class="doc-number">{{ $documentTitle ?? 'INVOICE' }} / {{ $documentNumber ?? '03' }}</div>
                </div>
            </div>

            <div class="content">
                @yield('content')
            </div>

            <!-- Footer -->
            <div style="padding: 0 40px 40px 40px;">
                <div class="footer">
                    <div class="footer-left">
                        <strong style="color:#c9a84c;display:block;margin-bottom:4px;">NEXT GEN RELOCATION LTD</strong>
                        47 Grasmere Avenue, Slough, SL2 5JD, United Kingdom
                    </div>
                    <div class="footer-center">
                        NEXT GEN, NEXT HOME, NEXT CHAPTER
                    </div>
                    <div class="footer-right">
                        Company Registration No: 17212822<br>
                        VAT Registration No: GB 522120648
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if(!empty($trackingUrl))
        <img src="{{ $trackingUrl }}" width="1" height="1" style="display:none;" alt="" />
    @endif
</body>
</html>
