@extends('lab.layouts.app')

@section('title', 'Append-Only Audit Trail | LIS')
@section('context', 'global')

@section('content')
<div class="lis-card">
    <div class="lis-card-header">
        <span>Immutable LIS Event & Transition Audit Trail</span>
        <span style="font-size: 11px; color: var(--lis-text-muted);">
            Append-only state machine log with user, timestamps and payload snapshots
        </span>
    </div>

    {{-- Filter by order ID --}}
    <form method="GET" action="{{ route('lab.audit.index') }}" style="display: flex; gap: 8px; margin-bottom: 8px;">
        <input type="number" name="order_id" class="form-control" placeholder="Filter by Order ID..." value="{{ $orderId }}" style="max-width: 220px;">
        <button type="submit" class="lis-btn lis-btn-primary">Filter</button>
        @if($orderId)
            <a href="{{ route('lab.audit.index') }}" class="lis-btn">Clear</a>
        @endif
    </form>

    <div class="lis-table-wrap" style="height: calc(100vh - 165px);">
        <table class="lis-table">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Timestamp</th>
                    <th>Order Accession</th>
                    <th>Patient</th>
                    <th>State Transition</th>
                    <th>Staff User</th>
                    <th>Audit Metadata (Payload)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                <tr>
                    <td>#{{ $event->id }}</td>
                    <td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td><strong>{{ $event->order?->accession_no ?? 'N/A' }}</strong></td>
                    <td>{{ $event->order?->patient?->full_name ?? 'N/A' }}</td>
                    <td>
                        <span class="badge" style="background: #e2e8f0; color: #475569;">
                            {{ $event->from_state ?: 'NEW' }}
                        </span>
                        →
                        <span class="badge badge-ordered">
                            {{ $event->to_state }}
                        </span>
                    </td>
                    <td>{{ $event->user?->name ?? 'System' }}</td>
                    <td>
                        @if(!empty($event->meta))
                            <pre style="font-size: 10px; background: var(--lis-surface-2); padding: 4px; border-radius: 3px; max-height: 80px; overflow-y: auto;">{{ json_encode($event->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        @else
                            <span style="color: var(--lis-text-muted); font-size: 10px;">None</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--lis-text-muted); padding: 30px;">
                        No audit events found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top: 8px;">
        {{ $events->withQueryString()->links() }}
    </div>
</div>
@endsection

