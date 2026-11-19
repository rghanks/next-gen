@extends('lab.layouts.app')

@section('title', 'Released Diagnostic Reports | LIS')
@section('context', 'reports')
@section('page-title', 'Reports & dispatch')
@section('page-description', 'View released reports, download signed PDFs, and manage amendments.')

@section('content')
<div class="lis-card lis-worklist-card">
    <div class="lis-card-header">
        <span>Released Diagnostic Reports & Dispatches</span>
        <span style="font-size: 11px; color: var(--lis-text-muted);">
            Print: <span class="kbd-hint">Alt+R</span> | WhatsApp: <span class="kbd-hint">Alt+W</span>
        </span>
    </div>

    {{-- Search bar --}}
    <form method="GET" action="{{ route('lab.reports.index') }}" class="lis-filter-bar">
        <div class="form-group lis-filter-search"><label class="form-label" for="report-search">Find report</label><input type="search" id="report-search" name="q" class="form-control" placeholder="Accession, patient name or UHID" value="{{ $term }}"></div>
        <button type="submit" class="lis-btn lis-btn-primary">Search</button>
        @if($term)
            <a href="{{ route('lab.reports.index') }}" class="lis-btn">Clear</a>
        @endif
    </form>

    {{-- Reports table --}}
    <div class="lis-table-wrap" style="height: calc(100vh - 165px);">
        <table class="lis-table" id="reports-table">
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>Accession #</th>
                    <th>Patient (UHID)</th>
                    <th>Age/Sex</th>
                    <th>Released Version</th>
                    <th>SHA-256 Checksum</th>
                    <th>Released At</th>
                    <th>Verified By</th>
                    <th style="text-align: right;">Dispatch & Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $idx => $order)
                @php
                    $latestReport = $order->latestReport;
                @endphp
                <tr data-index="{{ $idx }}" data-order-id="{{ $order->id }}">
                    <td>{{ $idx + 1 }}</td>
                    <td><strong>{{ $order->accession_no }}</strong></td>
                    <td>
                        {{ $order->patient?->full_name }}
                        <div style="font-size: 10px; color: var(--lis-text-muted);">UHID: {{ $order->patient?->uhid ?: 'N/A' }} | Ph: {{ $order->patient?->mobile_no ?: 'N/A' }}</div>
                    </td>
                    <td>{{ $order->patient?->age }}Y / {{ strtoupper($order->patient?->sex ?? '') }}</td>
                    <td>
                        @if($latestReport && $latestReport->version > 1)
                            <span class="badge badge-cancelled">
                                v{{ $latestReport->version }} (Amended)
                            </span>
                        @else
                            <span class="badge badge-verified">v{{ $latestReport?->version ?? 1 }} Final</span>
                        @endif
                    </td>
                    <td>
                        @if($latestReport && $latestReport->pdf_sha256)
                            <code style="font-size: 9px;" title="{{ $latestReport->pdf_sha256 }}">
                                {{ substr($latestReport->pdf_sha256, 0, 12) }}...
                            </code>
                        @else
                            <span style="color: var(--lis-text-muted); font-size: 10px;">Generated on demand</span>
                        @endif
                    </td>
                    <td>{{ $latestReport?->released_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                    <td>{{ $latestReport?->verifiedByUser?->name ?? 'Pathologist' }}</td>
                    <td style="text-align: right;">
                        <a href="{{ route('lab.reports.view', $order->id) }}" target="_blank" class="lis-btn" style="height: 22px; font-size: 10px;">
                            View
                        </a>
                        <a href="{{ route('lab.reports.pdf', $order->id) }}" class="lis-btn lis-btn-primary btn-print-pdf" style="height: 22px; font-size: 10px;">
                            PDF ↓
                        </a>
                        <button type="button" class="lis-btn btn-send-whatsapp" data-order-id="{{ $order->id }}" style="height: 22px; font-size: 10px;" title="Dispatch WhatsApp">
                            WhatsApp
                        </button>
                        <button type="button" class="lis-btn btn-open-amend" data-order-id="{{ $order->id }}" data-accession="{{ $order->accession_no }}" style="height: 22px; font-size: 10px;">
                            Amend
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align: center; color: var(--lis-text-muted); padding: 30px;">
                        No released reports found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top: 8px;">
        {{ $orders->withQueryString()->links() }}
    </div>
</div>

{{-- Amendment Dialog --}}
<div id="amend-modal" class="lis-modal-overlay">
    <div class="lis-modal">
        <div class="lis-modal-header">
            <span>Amend Diagnostic Report</span>
            <button type="button" class="lis-btn" onclick="$('#amend-modal').hide()">Esc</button>
        </div>
        <input type="hidden" id="amend-order-id">
        <div style="font-size: 11px; color: var(--lis-text-muted); margin-bottom: 6px;">
            Order Accession: <strong id="amend-accession-lbl"></strong><br>
            Amending will create Version N+1, preserve the previous signed PDF immutably, and stamp the new release as "AMENDED REPORT".
        </div>
        <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label">Mandatory Clinical / Technical Amendment Reason *</label>
            <textarea id="amend-reason" class="form-control" style="height: 60px; resize: none;" placeholder="e.g. Corrected reference range calculation following physician consult..."></textarea>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 6px;">
            <button type="button" class="lis-btn" onclick="$('#amend-modal').hide()">Cancel</button>
            <button type="button" id="btn-confirm-amend" class="lis-btn lis-btn-primary">Release Amended Version</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    $(document).on('click', '.btn-send-whatsapp', function() {
        const oid = $(this).data('order-id');
        const btn = $(this).prop('disabled', true).text('Sending...');

        $.post("{{ url('/lab/reports') }}/" + oid + "/whatsapp", function(res) {
            btn.prop('disabled', false).text('WhatsApp');
            window.lisToast(res.message, 'success');
        }).fail(function(xhr) {
            btn.prop('disabled', false).text('WhatsApp');
            window.lisToast(xhr.responseJSON?.message || 'Failed to dispatch WhatsApp.', 'danger');
        });
    });

    $(document).on('click', '.btn-open-amend', function() {
        const oid = $(this).data('order-id');
        const acc = $(this).data('accession');
        $('#amend-order-id').val(oid);
        $('#amend-accession-lbl').text(acc);
        $('#amend-reason').val('');
        $('#amend-modal').css('display', 'flex');
        $('#amend-reason').focus();
    });

    $('#btn-confirm-amend').on('click', function() {
        const oid = $('#amend-order-id').val();
        const reason = $('#amend-reason').val().trim();

        if (reason.length < 5) {
            window.lisToast('Please enter a valid amendment reason (at least 5 characters).', 'danger');
            return;
        }

        const btn = $(this).prop('disabled', true).text('Amending...');

        $.post("{{ url('/lab/reports') }}/" + oid + "/amend", {
            amend_reason: reason
        }, function(res) {
            window.lisToast(res.message, 'success');
            $('#amend-modal').hide();
            setTimeout(() => window.location.reload(), 500);
        }).fail(function(xhr) {
            btn.prop('disabled', false).text('Release Amended Version');
            window.lisToast(xhr.responseJSON?.message || 'Failed to amend report.', 'danger');
        });
    });

    // Shortcuts: Alt+R prints PDF of selected row, Alt+W dispatches WhatsApp
    if (window.Shortcuts) {
        window.Shortcuts.register('reports', 'alt+r', () => {
            const row = $('#reports-table tbody tr.selected, #reports-table tbody tr:first');
            const pdfBtn = row.find('.btn-print-pdf');
            if (pdfBtn.length) window.location.href = pdfBtn.attr('href');
        }, 'Download / Print Report PDF');

        window.Shortcuts.register('reports', 'alt+w', () => {
            const row = $('#reports-table tbody tr.selected, #reports-table tbody tr:first');
            row.find('.btn-send-whatsapp').trigger('click');
        }, 'Send via WhatsApp');
    }
});
</script>
@endpush
