@extends('lab.layouts.app')

@section('title', 'Pathologist Verification Queue | LIS')
@section('context', 'worklists')
@section('page-title', 'Verification queue')
@section('page-description', 'Review observations and critical acknowledgements before releasing reports.')

@section('content')
<div class="lis-card lis-worklist-card">
    <div class="lis-card-header">
        <span>Awaiting review <span class="badge">{{ $orders->total() }}</span></span>
        <div style="display: flex; gap: 6px;">
            <button type="button" id="btn-batch-verify" class="lis-btn lis-btn-primary">
                Verify & Release Selected (Ctrl+Enter)
            </button>
        </div>
    </div>

    {{-- Filter strip --}}
    <div style="display: flex; gap: 8px; align-items: center; background: var(--lis-surface-2); padding: 6px 8px; border-radius: var(--lis-radius); margin-bottom: 8px;">
        <span style="font-size: 11px; color: var(--lis-text-muted);">
            Pathologist Review Queue. All critical/panic values must have clinical acknowledgement before sign-off.
        </span>
    </div>

    {{-- Queue Table --}}
    <div class="lis-table-wrap" style="height: calc(100vh - 165px);">
        <table class="lis-table" id="verify-queue-table">
            <thead>
                <tr>
                    <th style="width: 25px;"><input type="checkbox" id="check-all-verify"></th>
                    <th>Accession #</th>
                    <th>Priority</th>
                    <th>Patient (UHID)</th>
                    <th>Age/Sex</th>
                    <th>Investigation Results Preview</th>
                    <th>Critical Flags</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $idx => $order)
                @php
                    $criticalVals = [];
                    foreach ($order->items as $item) {
                        foreach ($item->resultValues as $val) {
                            if (in_array($val->flag, ['LL', 'HH'])) {
                                $criticalVals[] = $val;
                            }
                        }
                    }
                    $hasUnackCritical = false;
                    foreach ($criticalVals as $cv) {
                        if (!$cv->criticalAcknowledgement) $hasUnackCritical = true;
                    }
                @endphp
                <tr data-index="{{ $idx }}" data-order-id="{{ $order->id }}">
                    <td>
                        <input type="checkbox" class="verify-checkbox" value="{{ $order->id }}" {{ $hasUnackCritical ? 'disabled' : '' }}>
                    </td>
                    <td><strong>{{ $order->accession_no }}</strong></td>
                    <td>
                        @if($order->priority === 'stat')
                            <span class="badge" style="background: var(--lis-danger); color: #fff;">STAT</span>
                        @else
                            <span class="badge badge-ordered">Routine</span>
                        @endif
                    </td>
                    <td>
                        {{ $order->patient?->full_name }}
                        <div style="font-size: 10px; color: var(--lis-text-muted);">UHID: {{ $order->patient?->uhid ?: 'N/A' }}</div>
                    </td>
                    <td>{{ $order->patient?->age }}Y / {{ strtoupper($order->patient?->sex ?? '') }}</td>
                    <td>
                        @foreach($order->items as $item)
                            <div style="font-size: 11px;">
                                <strong>{{ $item->service?->service }}:</strong>
                                @foreach($item->resultValues->take(3) as $rv)
                                    <span>{{ $rv->parameter?->short_code }}: {{ $rv->display_value }} ({{ $rv->flag }})</span>
                                @endforeach
                            </div>
                        @endforeach
                    </td>
                    <td>
                        @if(count($criticalVals) > 0)
                            <span class="badge flag-hh">
                                {{ count($criticalVals) }} Critical
                                {{ $hasUnackCritical ? '(Unacked)' : '(Acked)' }}
                            </span>
                        @else
                            <span class="badge flag-n">None</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $order->status->badgeClass() }}">
                            {{ $order->status->label() }}
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <a href="{{ route('lab.results.entry', $order->id) }}" class="lis-btn" style="height: 22px; font-size: 10px;">
                            Review
                        </a>
                        <button type="button" class="lis-btn lis-btn-primary btn-single-verify" data-order-id="{{ $order->id }}" {{ $hasUnackCritical ? 'disabled' : '' }} style="height: 22px; font-size: 10px;">
                            Sign-off & Release
                        </button>
                        <button type="button" class="lis-btn lis-btn-danger btn-reject-back" data-order-id="{{ $order->id }}" style="height: 22px; font-size: 10px;">
                            Reject Back
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align: center; color: var(--lis-text-muted); padding: 30px;">
                        No pending results in the verification queue.
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

{{-- Reject back modal --}}
<div id="reject-back-modal" class="lis-modal-overlay">
    <div class="lis-modal">
        <div class="lis-modal-header">
            <span>Reject Back to Technician</span>
            <button type="button" class="lis-btn" onclick="$('#reject-back-modal').hide()">Esc</button>
        </div>
        <input type="hidden" id="reject-order-id">
        <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label">Pathologist Remarks / Re-run instructions *</label>
            <textarea id="reject-remarks" class="form-control" style="height: 60px; resize: none;" placeholder="e.g. Inconsistent with clinical diagnosis; please re-run on dilution 1:10..."></textarea>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 6px;">
            <button type="button" class="lis-btn" onclick="$('#reject-back-modal').hide()">Cancel</button>
            <button type="button" id="btn-confirm-reject-back" class="lis-btn lis-btn-danger">Send Back</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    $('#check-all-verify').on('change', function() {
        $('.verify-checkbox:not(:disabled)').prop('checked', $(this).prop('checked'));
    });

    $('#btn-batch-verify').on('click', function() {
        const checked = $('.verify-checkbox:checked').map((_, el) => $(el).val()).get();
        if (!checked.length) {
            window.lisToast('Please select orders to verify.', 'danger');
            return;
        }
        verifyOrders(checked);
    });

    $(document).on('click', '.btn-single-verify', function() {
        const oid = $(this).data('order-id');
        verifyOrders([oid]);
    });

    function verifyOrders(orderIds) {
        $.post("{{ route('lab.verification.verify') }}", {
            order_ids: orderIds
        }, function(res) {
            window.lisToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 400);
        }).fail(function(xhr) {
            window.lisToast(xhr.responseJSON?.message || 'Verification failed.', 'danger');
        });
    }

    $(document).on('click', '.btn-reject-back', function() {
        const oid = $(this).data('order-id');
        $('#reject-order-id').val(oid);
        $('#reject-remarks').val('');
        $('#reject-back-modal').css('display', 'flex');
        $('#reject-remarks').focus();
    });

    $('#btn-confirm-reject-back').on('click', function() {
        const oid = $('#reject-order-id').val();
        const remarks = $('#reject-remarks').val().trim();

        if (!remarks) {
            window.lisToast('Please enter remarks for the technician.', 'danger');
            return;
        }

        $.post("{{ route('lab.verification.reject') }}", {
            order_id: oid,
            remarks: remarks
        }, function(res) {
            window.lisToast(res.message, 'success');
            $('#reject-back-modal').hide();
            setTimeout(() => window.location.reload(), 400);
        }).fail(function(xhr) {
            window.lisToast(xhr.responseJSON?.message || 'Failed to reject back.', 'danger');
        });
    });

    // Verification shortcut Ctrl+Enter
    if (window.Shortcuts) {
        window.Shortcuts.register('worklists', 'ctrl+enter', () => $('#btn-batch-verify').trigger('click'), 'Verify Selected Orders');
    }
});
</script>
@endpush
