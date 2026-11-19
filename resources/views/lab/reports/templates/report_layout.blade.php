@php
    // Demographic details
    $patient = $order->patient;
    $rawName = ($patient?->full_name ?: trim(($patient?->Fname ?? '') . ' ' . ($patient?->Lname ?? '')) ?: 'Patient #' . $order->patient_id);
    $prefix = $patient?->prefix ? trim($patient->prefix) : '';
    if ($prefix && !str_starts_with(strtolower(trim($rawName)), strtolower($prefix))) {
        $fullName = $prefix . ' ' . $rawName;
    } else {
        $fullName = $rawName;
    }
    $uhid = $patient?->uhid ?: ($patient?->id ?: '-');
    $mobileNo = $patient?->mobile_no ?: '-';

    $ageStr = $patient?->age ? $patient->age . ' Yr' : '';
    $sex = strtoupper($patient?->sex ?? '');
    $sexLabel = in_array($sex, ['M', 'MALE']) ? 'Male' : (in_array($sex, ['F', 'FEMALE']) ? 'Female' : ($sex ?: 'Male'));
    $ageGender = trim($ageStr . ' / ' . $sexLabel);

    $printedOn = date('d-m-Y h:i A');
    $registeredOn = $order->created_at ? $order->created_at->format('d-m-Y h:i A') : date('d-m-Y h:i A');
    $refDoctor = $patient?->referring_doctor ?: ($order->visit?->doctor?->name ?? 'Dr. Kamal Kishor');

    // Laboratory Settings & Dynamic Branding
    $labSetting = $labSetting ?? \App\Models\LabSetting::current();
    $labName = $labSetting->lab_name ?: 'CENTRAL HOSPITAL LABORATORY';
    $labTagline = $labSetting->tagline ?: 'Department of Pathology & Clinical Diagnostics';
    $labAccreditation = $labSetting->accreditation_text ?: 'Fully Automated Computerized Diagnostic Lab • Accredited Quality Care';
    $labGst = $labSetting->gst_number;
    $labPhone = $labSetting->mobile_no;
    $labAddress = trim(($labSetting->address_line1 ?? '') . ' ' . ($labSetting->address_line2 ?? ''));

    // Visuals & Signatures inline base64 URI
    $logoUri = $labSetting->getLogoUri();
    $headerImageUri = $labSetting->getHeaderImageUri();
    $footerImageUri = $labSetting->getFooterImageUri();
    $technicianSignUri = $labSetting->getTechnicianSignUri();
    $pathologistSignUri = $labSetting->getPathologistSignUri();

    // Top and Bottom Margins (configured in mm)
    $effectiveTopMargin = $topMargin ?? ($withLetterhead ? 8 : ($labSetting->print_top_margin ?? 35));
    $effectiveBottomMargin = $bottomMargin ?? ($labSetting->print_bottom_margin ?? 20);

    // Filter Items to Report (Exclude cancelled, support specific selected item)
    $reportItems = $order->items->filter(function ($item) {
        return $item->status !== \App\Domain\Lab\States\LabState::CANCELLED;
    });

    $activeItemId = $selectedItemId ?? request('item_id');
    if (!empty($activeItemId)) {
        $filtered = $reportItems->where('id', (int) $activeItemId);
        if ($filtered->isNotEmpty()) {
            $reportItems = $filtered;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic Report - {{ $order->accession_no }} (v{{ $report->version }})</title>
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        @page {
            margin: {{ $effectiveTopMargin }}mm 12mm {{ $effectiveBottomMargin }}mm 12mm;
            size: A4 portrait;
            @bottom-right {
                content: "Page " counter(page) " of " counter(pages);
                font-size: 8pt;
                color: #64748b;
            }
        }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 10.5px;
            color: #000000;
            line-height: 1.3;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        /* Screen Preview Styling */
        @if(empty($isPdf))
        body {
            background-color: #f1f5f9;
            padding: 20px 10px;
        }
        .report-page {
            background-color: #ffffff;
            max-width: 210mm;
            min-height: 275mm;
            margin: 0 auto 30px auto;
            padding: 12mm 14mm;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-radius: 4px;
            box-sizing: border-box;
            position: relative;
        }
        .page-break {
            display: none;
        }
        @else
        .report-page {
            width: 100%;
            margin: 0;
            padding: 0;
        }
        .page-break {
            page-break-after: always;
            break-after: page;
            height: 0;
            line-height: 0;
            clear: both;
        }
        @endif

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .report-page {
                background: #ffffff !important;
                max-width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                page-break-inside: avoid;
            }
            .page-break {
                display: block !important;
                page-break-after: always !important;
                break-after: page !important;
                height: 0 !important;
                line-height: 0 !important;
                clear: both !important;
            }
        }

        /* Demographic Box */
        .patient-demographic-box {
            border: 1px solid #000000;
            padding: 4px 8px;
            margin-bottom: 6px;
            background-color: #ffffff;
            page-break-inside: avoid;
        }

        .demographic-table {
            width: 100%;
            border-collapse: collapse;
        }

        .demographic-table td {
            vertical-align: top;
            padding: 1px 3px;
            font-size: 10.5px;
            line-height: 1.25;
        }

        .demographic-table .field-label {
            color: #1e293b;
            white-space: nowrap;
        }

        .demographic-table .field-sep {
            width: 10px;
            text-align: center;
            font-weight: bold;
        }

        .demographic-table .field-value {
            font-weight: 700;
            color: #000000;
        }

        /* Department Header */
        .department-heading {
            text-align: center;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            margin: 6px 0 6px 0;
            color: #000000;
            page-break-inside: avoid;
        }

        /* Results Table */
        .report-results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .report-results-table thead tr {
            border-top: 1.5px solid #000000;
            border-bottom: 1.5px solid #000000;
        }

        .report-results-table thead th {
            padding: 4px 6px;
            font-size: 10.5px;
            font-weight: 700;
            color: #000000;
            text-align: left;
            background-color: #ffffff;
        }

        .report-results-table tbody tr.test-header-row td {
            font-weight: 800;
            font-size: 11.5px;
            padding: 5px 6px 3px 6px;
            color: #000000;
            background-color: #f1f5f9;
            border: none;
            page-break-inside: avoid;
        }

        .report-results-table tbody tr.param-row {
            page-break-inside: avoid;
        }

        .report-results-table tbody tr.param-row td {
            padding: 2.5px 6px;
            font-size: 10.5px;
            vertical-align: middle;
            border: none;
            line-height: 1.2;
        }

        .report-results-table tbody tr.row-even {
            background-color: #f8fafc;
        }

        .report-results-table tbody tr.row-odd {
            background-color: #ffffff;
        }

        .val-abnormal {
            color: #dc2626 !important;
            font-weight: 700 !important;
        }

        .val-normal {
            color: #000000;
            font-weight: 500;
        }

        /* End of Report */
        .report-end-divider {
            border-top: 1px dashed #64748b;
            margin-top: 6px;
            padding-top: 4px;
            text-align: center;
            font-size: 9px;
            color: #64748b;
            letter-spacing: 0.5px;
            page-break-inside: avoid;
        }

        /* Signatures Section */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            page-break-inside: avoid;
        }

        .signature-table td {
            vertical-align: top;
            text-align: center;
            padding: 0 12px;
        }

        .sig-name {
            font-weight: 700;
            font-size: 11px;
            color: #000000;
            margin-top: 2px;
        }

        .sig-desig {
            font-size: 9.5px;
            color: #334155;
            line-height: 1.25;
        }

        .amended-alert {
            background-color: #fef2f2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 4px 8px;
            border-radius: 4px;
            margin-bottom: 6px;
            font-size: 10px;
            font-weight: bold;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>

    {{-- Screen-only Toolbar (hidden during print & in PDF generation) --}}
    @if(empty($isPdf))
    <div class="no-print" style="max-width: 210mm; margin: 0 auto 12px auto;">
        <table style="width: 100%; background: #1e293b; color: #ffffff; padding: 6px 12px; border-radius: 4px; border-collapse: collapse;">
            <tr>
                <td style="font-weight: bold; font-size: 12px; color: #ffffff; vertical-align: middle; padding: 6px 10px;">
                    📄 Diagnostic Report Preview: {{ $order->accession_no }} (v{{ $report->version }})
                    @if(!empty($activeItemId))
                        <span style="font-size: 11px; font-weight: normal; background: #0369a1; padding: 2px 7px; border-radius: 3px; margin-left: 6px;">Single Test View</span>
                        <a href="{{ route('lab.reports.view', $order->id) }}?version={{ $report->version }}" style="color: #38bdf8; font-size: 11px; margin-left: 8px; text-decoration: underline;">View All Tests (Page by Page)</a>
                    @elseif($reportItems->count() > 1)
                        <span style="font-size: 11px; font-weight: normal; color: #94a3b8; margin-left: 6px;">({{ $reportItems->count() }} Tests · 1 Page Each)</span>
                    @endif
                </td>
                <td style="text-align: right; vertical-align: middle; padding: 6px 10px;">
                    <button type="button" onclick="window.print()" style="background: #475569; color: #ffffff; border: none; padding: 5px 12px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 11px; margin-right: 6px;">
                        🖨️ Browser Print
                    </button>
                    <a href="{{ route('lab.reports.print', $order->id) }}?version={{ $report->version }}{{ !empty($activeItemId) ? '&item_id=' . $activeItemId : '' }}" target="_blank" style="background: #0d9488; color: #ffffff; text-decoration: none; padding: 5px 14px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 11px; margin-right: 8px; display: inline-block;">
                        📄 PDF Print
                    </a>
                    <a href="{{ route('lab.reports.pdf', $order->id) }}?version={{ $report->version }}{{ !empty($activeItemId) ? '&item_id=' . $activeItemId : '' }}" style="background: #0284c7; color: #ffffff; text-decoration: none; padding: 5px 14px; border-radius: 4px; font-weight: bold; font-size: 11px; display: inline-block;">
                        ⬇️ Download PDF
                    </a>
                </td>
            </tr>
        </table>
    </div>
    @endif

    {{-- Render Each Test / Investigation as a Single Report on 1 Page --}}
    @forelse($reportItems as $item)
        @php
            $serviceName = strtoupper($item->service?->service ?? '');
            $categoryName = strtoupper($item->service?->category?->name ?? '');

            if (str_contains($serviceName, 'CBC') || str_contains($serviceName, 'BLOOD') || str_contains($serviceName, 'HEMO') || str_contains($serviceName, 'HAEM') || str_contains($serviceName, 'COUNT') || str_contains($serviceName, 'PLATELET') || str_contains($serviceName, 'ESR')) {
                $itemDept = 'HAEMATOLOGY';
            } elseif (str_contains($serviceName, 'LIVER') || str_contains($serviceName, 'KIDNEY') || str_contains($serviceName, 'LFT') || str_contains($serviceName, 'KFT') || str_contains($serviceName, 'RFT') || str_contains($serviceName, 'LIPID') || str_contains($serviceName, 'SUGAR') || str_contains($serviceName, 'GLUCOSE') || str_contains($serviceName, 'AMYLASE') || str_contains($serviceName, 'CHOLESTEROL') || str_contains($serviceName, 'CREATININE') || str_contains($serviceName, 'UREA') || str_contains($serviceName, 'BILIRUBIN') || str_contains($serviceName, 'CALCIUM') || str_contains($serviceName, 'ELECTROLYTE')) {
                $itemDept = 'BIOCHEMISTRY';
            } elseif (str_contains($serviceName, 'URINE') || str_contains($serviceName, 'STOOL') || str_contains($serviceName, 'SEMEN') || str_contains($serviceName, 'SPUTUM')) {
                $itemDept = 'CLINICAL PATHOLOGY';
            } elseif (str_contains($serviceName, 'WIDAL') || str_contains($serviceName, 'HIV') || str_contains($serviceName, 'HBSAG') || str_contains($serviceName, 'HCV') || str_contains($serviceName, 'SEROLOGY') || str_contains($serviceName, 'VDRL') || str_contains($serviceName, 'DENGUE') || str_contains($serviceName, 'TYPHI') || str_contains($serviceName, 'CRP') || str_contains($serviceName, 'RA FACTOR') || str_contains($serviceName, 'ASO')) {
                $itemDept = 'SEROLOGY & IMMUNOLOGY';
            } elseif (str_contains($serviceName, 'THYROID') || str_contains($serviceName, 'T3') || str_contains($serviceName, 'T4') || str_contains($serviceName, 'TSH') || str_contains($serviceName, 'HORMONE') || str_contains($serviceName, 'VITAMIN') || str_contains($serviceName, 'FERRITIN') || str_contains($serviceName, 'B12')) {
                $itemDept = 'ENDOCRINOLOGY / SPECIAL TESTS';
            } elseif (str_contains($serviceName, 'CULTURE') || str_contains($serviceName, 'GRAM') || str_contains($serviceName, 'SENSITIVITY') || str_contains($serviceName, 'FUNGAL')) {
                $itemDept = 'MICROBIOLOGY';
            } elseif (!empty($categoryName) && !in_array($categoryName, ['GENERAL', 'DEFAULT', 'OTHER'])) {
                $itemDept = $categoryName;
            } else {
                $itemDept = 'CLINICAL LABORATORY';
            }

            // Specimen type if collected
            $specimen = ($item->relationLoaded('specimens') ? $item->specimens->first() : null)
                ?? ($order->relationLoaded('specimens') ? $order->specimens->first() : null);
            $specimenName = $specimen?->specimen_type ? ucfirst($specimen->specimen_type) : null;
        @endphp

        <div class="report-page">
            {{-- Clinic / Hospital Letterhead Header --}}
            @if($withLetterhead)
                @if($headerImageUri)
                    <div style="width: 100%; margin-bottom: 6px; text-align: center;">
                        <img src="{{ $headerImageUri }}" alt="Header Letterhead" style="width: 100%; max-height: {{ $labSetting->header_height ?? 110 }}px;">
                    </div>
                @else
                    <table style="width: 100%; margin-bottom: 8px; border-collapse: collapse;">
                        <tr>
                            @if($logoUri)
                            <td style="width: 13%; vertical-align: middle; padding-bottom: 4px; border-bottom: 2px solid #0f172a;">
                                <img src="{{ $logoUri }}" alt="Logo" style="max-height: 48px; max-width: 80px;">
                            </td>
                            @endif
                            <td style="width: {{ $logoUri ? '57%' : '70%' }}; vertical-align: top; padding-bottom: 4px; border-bottom: 2px solid #0f172a;">
                                <div style="font-size: 16px; font-weight: 800; color: #0f172a; letter-spacing: 0.5px; text-transform: uppercase;">{{ strtoupper($labName) }}</div>
                                <div style="font-size: 10px; color: #334155; font-weight: 600; margin-top: 1px;">{{ $labTagline }}</div>
                                <div style="font-size: 8.5px; color: #64748b;">{{ $labAccreditation }}</div>
                                @if($labAddress || $labPhone || $labGst)
                                <div style="font-size: 8px; color: #475569; margin-top: 2px;">
                                    @if($labAddress){{ $labAddress }} &nbsp;•&nbsp; @endif
                                    @if($labPhone)Phone: {{ $labPhone }} &nbsp;•&nbsp; @endif
                                    @if($labGst)GSTIN: {{ $labGst }}@endif
                                </div>
                                @endif
                            </td>
                            <td style="width: 30%; text-align: right; vertical-align: top; padding-bottom: 4px; border-bottom: 2px solid #0f172a;">
                                <div style="font-size: 11px; font-weight: 700; color: #0f172a; letter-spacing: 0.5px; margin-top: 2px;">Acc. No: {{ $order->accession_no }}</div>
                            </td>
                        </tr>
                    </table>
                @endif
            @endif

            {{-- Amended Report Notice if applicable --}}
            @if($report->isAmended())
            <div class="amended-alert">
                ⚠️ AMENDED REPORT (Version {{ $report->version }}) — Replaces Version {{ $report->version - 1 }}<br>
                <span style="font-weight: normal; font-size: 9px;">Reason: {{ $report->amend_reason }}</span>
            </div>
            @endif

            {{-- Patient Demographic Box (Full Benchmark Standard) --}}
            <div class="patient-demographic-box">
                <table class="demographic-table">
                    <tr>
                        <!-- Left Column -->
                        <td style="width: 50%; padding-right: 8px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td class="field-label" style="width: 85px;">UHID</td>
                                    <td class="field-sep">:</td>
                                    <td class="field-value">{{ $uhid }}</td>
                                </tr>
                                <tr>
                                    <td class="field-label">Patient Name</td>
                                    <td class="field-sep">:</td>
                                    <td class="field-value">{{ $fullName }}</td>
                                </tr>
                                <tr>
                                    <td class="field-label">Age / Gender</td>
                                    <td class="field-sep">:</td>
                                    <td class="field-value">{{ $ageGender }}</td>
                                </tr>
                                <tr>
                                    <td class="field-label">Mobile No.</td>
                                    <td class="field-sep">:</td>
                                    <td class="field-value">{{ $mobileNo }}</td>
                                </tr>
                            </table>
                        </td>

                        <!-- Right Column -->
                        <td style="width: 50%; padding-left: 8px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td class="field-label" style="width: 95px;">Printed On</td>
                                    <td class="field-sep">:</td>
                                    <td class="field-value">{{ $printedOn }}</td>
                                </tr>
                                <tr>
                                    <td class="field-label">Registered On</td>
                                    <td class="field-sep">:</td>
                                    <td class="field-value">{{ $registeredOn }}</td>
                                </tr>
                                <tr>
                                    <td class="field-label">Ref Doctor</td>
                                    <td class="field-sep">:</td>
                                    <td class="field-value">{{ $refDoctor }}</td>
                                </tr>
                                @if($specimenName)
                                <tr>
                                    <td class="field-label">Sample Type</td>
                                    <td class="field-sep">:</td>
                                    <td class="field-value">{{ $specimenName }}</td>
                                </tr>
                                @else
                                <tr>
                                    <td class="field-label">Account</td>
                                    <td class="field-sep">:</td>
                                    <td class="field-value" style="font-weight: normal;">-</td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                </table>
            </div>

            {{-- Department Heading --}}
            <div class="department-heading">
                {{ $itemDept }}
            </div>

            {{-- Investigation Results Table --}}
            <table class="report-results-table">
                <thead>
                    <tr>
                        <th style="width: 38%;">Parameter</th>
                        <th style="width: 18%;">Value</th>
                        <th style="width: 14%;">Unit</th>
                        <th style="width: 30%;">Biological Reference Range</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Test Title Heading -->
                    <tr class="test-header-row">
                        <td colspan="4">
                            {{ $item->service?->service ?: 'Investigation #' . $item->service_id }}
                            @if($item->is_outsource)
                                <span style="font-size: 9px; font-weight: normal; color: #64748b;">(Outsourced)</span>
                            @endif
                        </td>
                    </tr>

                    <!-- Parameters for this Test -->
                    @forelse($item->resultValues as $idx => $val)
                        @php
                            $displayVal = $val->display_value;
                            
                            // Check if abnormal
                            $isAbnormal = in_array($val->flag, ['H', 'HH', 'L', 'LL', 'A'], true);
                            if (! $isAbnormal && $val->value_numeric !== null && $val->ref_low !== null && $val->ref_high !== null) {
                                $isAbnormal = ($val->value_numeric < $val->ref_low || $val->value_numeric > $val->ref_high);
                            }

                            // Reference range string
                            $refDisplay = $val->ref_text;
                            if (! $refDisplay && $val->ref_low !== null && $val->ref_high !== null) {
                                $refDisplay = (0 + $val->ref_low) . ' - ' . (0 + $val->ref_high);
                            }
                            if (! $refDisplay) {
                                $refDisplay = '-';
                            }

                            $unitDisplay = $val->unit ?: ($val->parameter?->unit ?: '');
                            $rowZebra = ($idx % 2 === 0) ? 'row-even' : 'row-odd';
                            $paramName = $val->parameter?->name ?: 'Parameter #' . $val->parameter_id;
                        @endphp

                        <tr class="param-row {{ $rowZebra }}">
                            <td style="font-weight: 500; color: #000000;">
                                {{ $paramName }}
                            </td>
                            <td>
                                @if($isAbnormal)
                                    <span class="val-abnormal">{{ $displayVal }}</span>
                                @else
                                    <span class="val-normal">{{ $displayVal }}</span>
                                @endif
                            </td>
                            <td style="color: #1e293b;">
                                {{ $unitDisplay }}
                            </td>
                            <td style="color: #1e293b;">
                                {{ $refDisplay }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; color: #64748b; padding: 8px;">
                                No discrete parameters recorded for this test.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- End of Report Marker --}}
            <div class="report-end-divider">
                ---------- End of Report ----------
            </div>

            {{-- Signatures Block (Technician, Pathologist) --}}
            <table class="signature-table">
                <tr>
                    <!-- Left: Lab Technician -->
                    <td style="width: 50%; text-align: left; padding-left: 15px;">
                        @if($technicianSignUri)
                            <img src="{{ $technicianSignUri }}" alt="Signature {{ $labSetting->technician_name }}" style="height: 32px; display: block; margin-bottom: 2px;">
                        @else
                            <div style="height: 32px;"></div>
                        @endif
                        <div class="sig-name">{{ $labSetting->technician_name }}</div>
                        <div class="sig-desig">
                            {{ $labSetting->technician_designation }}
                            @if($labSetting->technician_degree)
                                <br><span style="font-size: 8.5px; color: #64748b;">{{ $labSetting->technician_degree }}</span>
                            @endif
                        </div>
                    </td>

                    <!-- Right: Consultant Pathologist -->
                    <td style="width: 50%; text-align: right; padding-right: 15px;">
                        @if($pathologistSignUri)
                            <img src="{{ $pathologistSignUri }}" alt="Signature {{ $labSetting->pathologist_name }}" style="height: 32px; display: inline-block; margin-bottom: 2px;">
                        @else
                            <div style="height: 32px;"></div>
                        @endif
                        <div class="sig-name">{{ $labSetting->pathologist_name }}</div>
                        <div class="sig-desig">
                            @if($labSetting->pathologist_degree)
                                {{ $labSetting->pathologist_degree }}<br>
                            @endif
                            {{ $labSetting->pathologist_designation }}
                        </div>
                    </td>
                </tr>
            </table>

            {{-- Footer Graphic OR Medico-Legal Disclaimer --}}
            @if($labSetting->use_letterhead_image && $footerImageUri)
                <div style="width: 100%; margin-top: 10px; text-align: center;">
                    <img src="{{ $footerImageUri }}" alt="Footer Letterhead" style="width: 100%; max-height: {{ $labSetting->footer_height ?? 60 }}px;">
                </div>
            @elseif($labSetting->report_disclaimer)
                <div style="margin-top: 8px; font-size: 8px; color: #64748b; text-align: center; letter-spacing: 0.3px;">
                    {{ $labSetting->report_disclaimer }}
                </div>
            @endif
        </div>

        {{-- Page break: pushes the NEXT report to the NEXT page (no break after the last test) --}}
        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @empty
        <div class="report-page" style="text-align: center; padding: 40px; color: #64748b;">
            <p style="font-size: 14px; font-weight: bold;">No active investigation reports found for this order.</p>
        </div>
    @endforelse

</body>
</html>