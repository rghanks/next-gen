@extends('lab.layouts.app')

@section('title', 'Specimen Management Worklist | LIS')
@section('context', 'worklists')
@section('page-title', 'Sample collection')
@section('page-description', 'Collect, receive, and track specimens from the billing desk to the bench.')

@section('content')
<div class="lis-card lis-worklist-card">
    <div class="lis-card-header">
        <span>Specimen worklist <span class="badge">{{ $specimens->total() }}</span></span>
        <div class="lis-segmented">
            <a href="{{ route('lab.specimens.index', ['tab' => 'pending']) }}" class="lis-btn {{ $tab === 'pending' ? 'lis-btn-primary' : '' }}">
                Pending (To Collect)
            </a>
            <a href="{{ route('lab.specimens.index', ['tab' => 'collected']) }}" class="lis-btn {{ $tab === 'collected' ? 'lis-btn-primary' : '' }}">
                Collected (In Transit)
            </a>
            <a href="{{ route('lab.specimens.index', ['tab' => 'received']) }}" class="lis-btn {{ $tab === 'received' ? 'lis-btn-primary' : '' }}">
                Received at Bench
            </a>
            <a href="{{ route('lab.specimens.index', ['tab' => 'rejected']) }}" class="lis-btn {{ $tab === 'rejected' ? 'lis-btn-primary' : '' }}">
                Rejected (Recollect)
            </a>
        </div>
    </div>

    {{-- Scanner-friendly barcode field --}}
    <div class="lis-filter-bar">
        <div class="form-group" style="flex: 1;">
            <label class="form-label" for="barcode_scanner_input">Sample barcode</label>
            <input type="search" id="barcode_scanner_input" class="form-control" placeholder="Scan or type barcode (Enter to process)..." autocomplete="off" autofocus>
        </div>
        <button type="button" id="btn-quick-collect" class="lis-btn lis-btn-primary">
            Quick Collect
        </button>
        <button type="button" id="btn-quick-receive" class="lis-btn">
            Quick Receive
        </button>
    </div>

    {{-- Worklist Data Table --}}
    <div class="lis-table-wrap" style="height: calc(100vh - 165px);">
        <table class="lis-table" id="specimen-table">
            <thead>
                <tr>
                    <th style="width: 25px;"><input type="checkbox" id="check-all"></th>
                    <th>Barcode</th>
                    <th>Accession #</th>
                    <th>Patient (UHID)</th>
                    <th>Age/Sex</th>
                    <th>Specimen & Container</th>
                    <th>Condition</th>
                    <th>Tests</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($specimens as $idx => $specimen)
                <tr data-index="{{ $idx }}" data-barcode="{{ $specimen->barcode }}">
                    <td><input type="checkbox" class="row-checkbox" value="{{ $specimen->barcode }}"></td>
                    <td><code>{{ $specimen->barcode }}</code></td>
                    <td><strong>{{ $specimen->order->accession_no }}</strong></td>
                    <td>
                        {{ $specimen->order->patient?->full_name }}
                        <div style="font-size: 10px; color: var(--lis-text-muted);">UHID: {{ $specimen->order->patient?->uhid ?: 'N/A' }}</div>
                    </td>
                    <td>{{ $specimen->order->patient?->age }}Y / {{ strtoupper($specimen->order->patient?->sex ?? '') }}</td>
                    <td>
                        <strong>{{ strtoupper($specimen->specimen_type) }}</strong>
                        <div style="font-size: 10px; color: var(--lis-text-muted);">{{ str_replace('_', ' ', $specimen->container) }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $specimen->condition === 'rejected' ? 'badge-cancelled' : 'badge-received' }}">
                            {{ ucfirst($specimen->condition) }}
                        </span>
                    </td>
                    <td>
                        @foreach($specimen->order->items as $item)
                            <span class="badge badge-ordered">{{ $item->service?->service }}</span>
                        @endforeach
                    </td>
                    <td>
                        <span class="badge {{ $specimen->order->status->badgeClass() }}">
                            {{ $specimen->order->status->label() }}
                        </span>
                    </td>
                    <td style="text-align: right;">
                        @if(!$specimen->collected_at)
                            <button type="button" class="lis-btn lis-btn-primary btn-action-collect" data-barcode="{{ $specimen->barcode }}" style="height: 22px; font-size: 10px;">
                                Collect
                            </button>
                        @elseif(!$specimen->received_at)
                            <button type="button" class="lis-btn btn-action-receive" data-barcode="{{ $specimen->barcode }}" style="height: 22px; font-size: 10px;">
                                Receive
                            </button>
                        @endif

                        @if($specimen->condition !== 'rejected')
                            <button type="button" class="lis-btn lis-btn-danger btn-action-reject" data-barcode="{{ $specimen->barcode }}" style="height: 22px; font-size: 10px;">
                                Reject
                            </button>
                        @endif

                        <a href="{{ route('lab.billing.barcode', $specimen->order->id) }}" target="_blank" class="lis-btn" style="height: 22px; font-size: 10px;" title="Print Tube Label">
                            🏷️ Label
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" style="text-align: center; color: var(--lis-text-muted); padding: 30px;">
                        No specimens found in this queue.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top: 8px;">
        {{ $specimens->withQueryString()->links() }}
    </div>
</div>

{{-- Reject Reason Dialog --}}
<div id="reject-modal" class="lis-modal-overlay">
    <div class="lis-modal">
        <div class="lis-modal-header">
            <span>Reject Specimen for Recollection</span>
            <button type="button" class="lis-btn" onclick="$('#reject-modal').hide()">Esc</button>
        </div>
        <input type="hidden" id="reject-barcode">
        <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label">Rejection Reason *</label>
            <select id="reject-reason-preset" class="form-control" style="margin-bottom: 4px;">
                <option value="Hemolyzed Sample">Hemolyzed Sample</option>
                <option value="Lipemic Sample">Lipemic Sample</option>
                <option value="Clotted Blood">Clotted Blood</option>
                <option value="Insufficient Quantity (QNS)">Insufficient Quantity (QNS)</option>
                <option value="Wrong Container / Anticoagulant">Wrong Container / Anticoagulant</option>
                <option value="Sample Leaked in Transit">Sample Leaked in Transit</option>
                <option value="Other">Other Reason</option>
            </select>
            <textarea id="reject-reason-custom" class="form-control" placeholder="Specify details..." style="height: 48px; resize: none;"></textarea>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 6px;">
            <button type="button" class="lis-btn" onclick="$('#reject-modal').hide()">Cancel</button>
            <button type="button" id="btn-confirm-reject" class="lis-btn lis-btn-danger">Confirm Rejection</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    // Barcode scanner auto submit on Enter
    $('#barcode_scanner_input').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = $(this).val().trim();
            if (!val) return;

            // Default action based on active tab
            @if($tab === 'pending')
                collectBarcodes([val]);
            @else
                receiveBarcode(val);
            @endif
            $(this).val('').focus();
        }
    });

    $('#btn-quick-collect').on('click', function() {
        const val = $('#barcode_scanner_input').val().trim();
        if (val) {
            collectBarcodes([val]);
            $('#barcode_scanner_input').val('').focus();
        } else {
            const checked = $('.row-checkbox:checked').map((_, el) => $(el).val()).get();
            if (checked.length) {
                collectBarcodes(checked);
            } else {
                window.lisToast('Please enter a barcode or select checkboxes.', 'danger');
            }
        }
    });

    $('#btn-quick-receive').on('click', function() {
        const val = $('#barcode_scanner_input').val().trim();
        if (val) {
            receiveBarcode(val);
            $('#barcode_scanner_input').val('').focus();
        } else {
            window.lisToast('Please enter or scan a barcode.', 'danger');
        }
    });

    $(document).on('click', '.btn-action-collect', function() {
        const barcode = $(this).data('barcode');
        collectBarcodes([barcode]);
    });

    $(document).on('click', '.btn-action-receive', function() {
        const barcode = $(this).data('barcode');
        receiveBarcode(barcode);
    });

    $(document).on('click', '.btn-action-reject', function() {
        const barcode = $(this).data('barcode');
        $('#reject-barcode').val(barcode);
        $('#reject-modal').css('display', 'flex');
        $('#reject-reason-custom').val('');
    });

    $('#btn-confirm-reject').on('click', function() {
        const barcode = $('#reject-barcode').val();
        let reason = $('#reject-reason-preset').val();
        const custom = $('#reject-reason-custom').val().trim();
        if (custom) reason = reason + ': ' + custom;

        $.post("{{ route('lab.specimens.reject') }}", {
            barcode: barcode,
            reason: reason
        }, function(res) {
            window.lisToast(res.message, 'success');
            $('#reject-modal').hide();
            setTimeout(() => window.location.reload(), 300);
        }).fail(function(xhr) {
            window.lisToast(xhr.responseJSON?.message || 'Failed to reject specimen.', 'danger');
        });
    });

    function collectBarcodes(barcodes) {
        $.post("{{ route('lab.specimens.collect') }}", {
            barcodes: barcodes
        }, function(res) {
            window.lisToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 400);
        }).fail(function(xhr) {
            window.lisToast(xhr.responseJSON?.message || 'Collection failed.', 'danger');
        });
    }

    function receiveBarcode(barcode) {
        $.post("{{ route('lab.specimens.receive') }}", {
            barcode: barcode,
            condition: 'normal'
        }, function(res) {
            window.lisToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 400);
        }).fail(function(xhr) {
            window.lisToast(xhr.responseJSON?.message || 'Receiving failed.', 'danger');
        });
    }

    // Check all toggle
    $('#check-all').on('change', function() {
        $('.row-checkbox').prop('checked', $(this).prop('checked'));
    });
});
</script>
@endpush
