<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Domain\Lab\Models\LabOrderEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    /**
     * Show append-only LIS event & audit log.
     */
    public function index(Request $request): View
    {
        $orderId = $request->input('order_id');

        $query = LabOrderEvent::with(['order.patient', 'user'])
            ->latest('id');

        if ($orderId) {
            $query->where('order_id', (int) $orderId);
        }

        $events = $query->paginate(30);

        return view('lab.audit.index', [
            'events' => $events,
            'orderId' => $orderId,
        ]);
    }
}
