@php
    $sigSpaceTop = $sigSpaceTop ?? 5;
    $sigSpaceBottom = $sigSpaceBottom ?? 5;
    $sigSpaceLeft = $sigSpaceLeft ?? 10;
    $sigSpaceRight = $sigSpaceRight ?? 10;
    $sigHeight = $sigHeight ?? 36;
    $signatureLayout = $signatureLayout ?? 'dual';
@endphp

<div class="signature-area-container" style="padding-top: {{ $sigSpaceTop }}mm; padding-bottom: {{ $sigSpaceBottom }}mm; padding-left: {{ $sigSpaceLeft }}mm; padding-right: {{ $sigSpaceRight }}mm; box-sizing: border-box; width: 100%;">
    <table class="signature-table" style="width: 100%; border-collapse: collapse; margin-top: 0; page-break-inside: avoid;">
        <tr>
            @if(in_array($signatureLayout, ['dual', 'left_only']))
                <!-- Left: Lab Technician / Left Signatory -->
                <td style="width: {{ $signatureLayout === 'left_only' ? '100%' : '50%' }}; text-align: left; vertical-align: bottom; padding: 0 4px;">
                    @if(!empty($techLabel))
                        <div class="sig-role-label" style="font-size: 9px; font-weight: 700; text-transform: uppercase; color: #475569; letter-spacing: 0.5px; margin-bottom: 3px;">
                            {{ $techLabel }}
                        </div>
                    @endif

                    @if($printWithSignature && $technicianSignUri)
                        <img src="{{ $technicianSignUri }}" alt="Signature {{ $labSetting->technician_name }}" style="height: {{ $sigHeight }}px; display: block; margin-bottom: 2px;">
                    @elseif(! $printWithSignature)
                        <div style="height: {{ $sigHeight }}px; display: block;">
                            <div style="border-bottom: 1px dotted #94a3b8; width: 140px; height: {{ max(10, $sigHeight - 4) }}px; margin-bottom: 4px;"></div>
                        </div>
                    @else
                        <div style="height: {{ $sigHeight }}px;"></div>
                    @endif

                    <div class="sig-name" style="font-weight: 700; font-size: 11px; color: #000000; margin-top: 2px;">{{ $labSetting->technician_name }}</div>
                    <div class="sig-desig" style="font-size: 9.5px; color: #334155; line-height: 1.25;">
                        {{ $labSetting->technician_designation }}
                        @if($labSetting->technician_degree)
                            <br><span style="font-size: 8.5px; color: #64748b;">{{ $labSetting->technician_degree }}</span>
                        @endif
                    </div>
                </td>
            @endif

            @if(in_array($signatureLayout, ['dual', 'right_only']))
                <!-- Right: Consultant Pathologist / Doctor / Right Signatory -->
                <td style="width: {{ $signatureLayout === 'right_only' ? '100%' : '50%' }}; text-align: right; vertical-align: bottom; padding: 0 4px;">
                    @if(!empty($pathLabel))
                        <div class="sig-role-label" style="font-size: 9px; font-weight: 700; text-transform: uppercase; color: #475569; letter-spacing: 0.5px; margin-bottom: 3px;">
                            {{ $pathLabel }}
                        </div>
                    @endif

                    @if($printWithSignature && $pathologistSignUri)
                        <img src="{{ $pathologistSignUri }}" alt="Signature {{ $labSetting->pathologist_name }}" style="height: {{ $sigHeight }}px; display: inline-block; margin-bottom: 2px;">
                    @elseif(! $printWithSignature)
                        <div style="height: {{ $sigHeight }}px; text-align: right; display: block;">
                            <div style="border-bottom: 1px dotted #94a3b8; width: 160px; height: {{ max(10, $sigHeight - 4) }}px; display: inline-block; margin-bottom: 4px;"></div>
                        </div>
                    @else
                        <div style="height: {{ $sigHeight }}px;"></div>
                    @endif

                    <div class="sig-name" style="font-weight: 700; font-size: 11px; color: #000000; margin-top: 2px;">{{ $labSetting->pathologist_name }}</div>
                    <div class="sig-desig" style="font-size: 9.5px; color: #334155; line-height: 1.25;">
                        @if($labSetting->pathologist_degree)
                            {{ $labSetting->pathologist_degree }}<br>
                        @endif
                        {{ $labSetting->pathologist_designation }}
                    </div>
                </td>
            @endif
        </tr>
    </table>
</div>
