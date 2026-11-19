<?php

declare(strict_types=1);

namespace App\Domain\Lab\Events;

use App\Domain\Lab\Models\LabBillCancellation;
use App\Domain\Lab\Models\LabOrder;
use App\Models\Legacy\PatientBilling;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LabBillCancelled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LabOrder $order,
        public readonly PatientBilling $bill,
        public readonly LabBillCancellation $cancellation
    ) {}
}
