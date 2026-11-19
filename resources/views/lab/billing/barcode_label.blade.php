<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tube Barcodes - {{ $order->accession_no }}</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 8px;
            font-size: 10px;
        }
        .barcode-label {
            width: 50mm;
            height: 25mm;
            border: 1px dotted #999;
            padding: 2mm;
            margin-bottom: 4mm;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .name { font-weight: bold; font-size: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .meta { font-size: 8px; color: #333; }
        .barcode-img { height: 18px; max-width: 100%; display: block; margin: 1px 0; }
        @media print {
            .barcode-label { border: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    @foreach($specimens as $item)
    <div class="barcode-label">
        <div class="name">{{ $order->patient?->full_name }}</div>
        <div class="meta">
            UHID: {{ $order->patient?->uhid ?: 'N/A' }} | {{ $order->patient?->age }}Y/{{ strtoupper($order->patient?->sex ?? '') }}
        </div>
        <div>
            @if(!empty($item['barcode_uri']))
                <img src="{{ $item['barcode_uri'] }}" class="barcode-img" alt="Barcode">
            @endif
        </div>
        <div class="meta" style="display: flex; justify-content: space-between;">
            <span>{{ $item['specimen']->barcode }}</span>
            <span style="font-weight: bold;">{{ strtoupper($item['specimen']->specimen_type) }}</span>
        </div>
    </div>
    @endforeach
</body>
</html>

