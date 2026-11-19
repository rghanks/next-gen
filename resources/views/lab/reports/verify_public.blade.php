<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Verification - {{ $order->accession_no }}</title>
    <link rel="stylesheet" href="{{ asset('css/lab.css') }}">
    <style>
        body {
            background-color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 16px;
        }
        .verify-card {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            max-width: 500px;
            width: 100%;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .shield-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <div class="shield-icon">🛡️</div>
        <h2 style="text-align: center; font-size: 18px; color: #15803d; margin-bottom: 4px;">
            Authentic Diagnostic Report
        </h2>
        <p style="text-align: center; font-size: 11px; color: #64748b; margin-bottom: 20px;">
            Cryptographically verified against Central Hospital Laboratory records
        </p>

        <table class="lis-table" style="margin-bottom: 16px;">
            <tr>
                <td style="color: #64748b; width: 40%;">Accession Number:</td>
                <td><strong>{{ $order->accession_no }}</strong></td>
            </tr>
            <tr>
                <td style="color: #64748b;">Report Version:</td>
                <td>
                    <strong>v{{ $report->version }}</strong>
                    @if($report->isAmended())
                        <span class="badge" style="background: #fef2f2; color: #991b1b;">Amended</span>
                    @else
                        <span class="badge badge-verified">Original</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="color: #64748b;">Patient Name:</td>
                <td><strong>{{ $order->patient?->full_name }}</strong></td>
            </tr>
            <tr>
                <td style="color: #64748b;">UHID:</td>
                <td><code>{{ $order->patient?->uhid ?: 'N/A' }}</code></td>
            </tr>
            <tr>
                <td style="color: #64748b;">Verified By:</td>
                <td>{{ $report->verifiedByUser?->name ?? 'Consultant Pathologist' }}</td>
            </tr>
            <tr>
                <td style="color: #64748b;">Released At:</td>
                <td>{{ $report->released_at?->format('d-M-Y H:i:s') }}</td>
            </tr>
            <tr>
                <td style="color: #64748b;">SHA-256 Checksum:</td>
                <td><code style="font-size: 10px; word-break: break-all;">{{ $report->pdf_sha256 }}</code></td>
            </tr>
        </table>

        @if($report->isAmended())
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; padding: 8px; font-size: 11px; margin-bottom: 16px;">
            <strong>Amendment Reason:</strong> {{ $report->amend_reason }}
        </div>
        @endif

        <div style="text-align: center;">
            <a href="{{ route('lab.reports.pdf', ['order' => $order->id, 'version' => $report->version]) }}" class="lis-btn lis-btn-primary" style="padding: 6px 16px;">
                Download Verified PDF Document ↓
            </a>
        </div>
    </div>
</body>
</html>

