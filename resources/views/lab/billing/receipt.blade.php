<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bill Receipt #{{ $order->bill?->bill_number }}</title>
    <style>
        @page {
            size: auto;
            margin: 4mm;
        }
        body {
            font-family: monospace, sans-serif;
            font-size: 12px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 10px;
        }
        .receipt-card {
            max-width: 380px;
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 12px;
            background: #fff;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .line { border-bottom: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { padding: 3px 0; }
        @media print {
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                color: #000 !important;
                visibility: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .receipt-card {
                border: none !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                visibility: visible !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-card">
        <div class="no-print center" style="margin-bottom: 10px;">
            <button type="button" onclick="window.print()" style="background: #0d9488; color: #fff; border: none; padding: 5px 14px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 11px; font-family: sans-serif;">
                🖨️ Print Receipt
            </button>
        </div>
        @php
            $labSetting = \App\Models\LabSetting::current();
        @endphp
        <div class="center bold" style="font-size: 14px;">{{ strtoupper($labSetting->lab_name) }}</div>
        <div class="center" style="font-size: 10px;">{{ $labSetting->tagline ?: 'Diagnostic & Clinical Pathology Center' }}</div>
        @if($labSetting->address_line1 || $labSetting->mobile_no || $labSetting->gst_number)
            <div class="center" style="font-size: 8.5px; color: #444; margin-top: 2px;">
                {{ $labSetting->address_line1 }} {{ $labSetting->address_line2 }}
                @if($labSetting->mobile_no) | Tel: {{ $labSetting->mobile_no }} @endif
                @if($labSetting->gst_number) | GST: {{ $labSetting->gst_number }} @endif
            </div>
        @endif
        <div class="center" style="margin: 4px 0;">
            @if(!empty($barcodeUri))
                <img src="{{ $barcodeUri }}" alt="Barcode" style="height: 30px;"><br>
            @endif
            <span class="bold">Accession: {{ $order->accession_no }}</span>
        </div>
        <div class="line"></div>

        <table>
            <tr>
                <td>Bill No: <strong>#{{ $order->bill?->bill_number }}</strong></td>
                <td class="right">Date: {{ $order->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td>Patient: <strong>{{ $order->patient?->full_name }}</strong></td>
                <td class="right">Age/Sex: {{ $order->patient?->age }}Y / {{ strtoupper($order->patient?->sex ?? '') }}</td>
            </tr>
            <tr>
                <td>UHID: {{ $order->patient?->uhid ?: 'N/A' }}</td>
                <td class="right">Phone: {{ $order->patient?->mobile_no ?: 'N/A' }}</td>
            </tr>
            <tr>
                <td colspan="2">Ref By: {{ $order->patient?->referring_doctor ?: 'Self' }}</td>
            </tr>
        </table>

        <div class="line"></div>

        <table>
            <thead>
                <tr class="bold">
                    <th>#</th>
                    <th>Test Description</th>
                    <th class="right">Price</th>
                </tr>
            </thead>
            <tbody>
                @php $subtotal = 0; @endphp
                @foreach($order->items as $idx => $item)
                    @php
                        $price = (float) ($item->legacyGenBill?->charges ?? 0);
                        $subtotal += $price;
                    @endphp
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $item->service?->service }}</td>
                        <td class="right">₹{{ number_format($price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="line"></div>

        @php
            $discount = (float) ($order->bill?->discount ?? 0);
            $net = max(0, $subtotal - $discount);
            $totalPaid = (float) ($order->bill?->payments->sum('amount') ?? 0);
            $due = max(0, $net - $totalPaid);
        @endphp

        <table>
            <tr>
                <td>Subtotal:</td>
                <td class="right">₹{{ number_format($subtotal, 2) }}</td>
            </tr>
            @if($discount > 0)
            <tr>
                <td>Discount:</td>
                <td class="right">- ₹{{ number_format($discount, 2) }}</td>
            </tr>
            @endif
            <tr class="bold">
                <td>Net Payable:</td>
                <td class="right">₹{{ number_format($net, 2) }}</td>
            </tr>
            <tr>
                <td>Total Received:</td>
                <td class="right">₹{{ number_format($totalPaid, 2) }}</td>
            </tr>
            @if($due > 0)
            <tr class="bold">
                <td>Balance Due:</td>
                <td class="right" style="color: red;">₹{{ number_format($due, 2) }}</td>
            </tr>
            @endif
        </table>

        <div class="line"></div>

        <div style="font-size: 10px;">
            Payment Modes:
            @foreach($order->bill?->payments ?? [] as $pay)
                <span>{{ strtoupper($pay->payment_mode) }}: ₹{{ number_format((float)$pay->amount, 2) }} </span>
            @endforeach
        </div>

        <div class="center" style="margin-top: 10px; font-size: 10px;">
            Thank you! Keep this receipt for collecting reports.<br>
            Online verification available via report QR code.
        </div>
    </div>

    <script>
        // If opened for print, auto-trigger print dialog and auto-close after print/cancel
        if (window.opener || window.name === 'ReceiptPrint' || window.location.search.includes('print=1')) {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.focus();
                    window.print();
                }, 200);
            });
            window.addEventListener('afterprint', function() {
                window.close();
            });
            window.onafterprint = function() {
                window.close();
            };
        }
    </script>
</body>
</html>

