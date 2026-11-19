<?php

declare(strict_types=1);

namespace App\Http\Resources\Lab;

use App\Domain\Lab\Models\LabOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property LabOrder $resource
 */
class LabOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'accession_no' => $this->resource->accession_no,
            'client_token' => $this->resource->client_token,
            'priority' => $this->resource->priority,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'badge_class' => $this->resource->status->badgeClass(),
            'clinical_notes' => $this->resource->clinical_notes,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'patient' => [
                'id' => $this->resource->patient?->id,
                'uhid' => $this->resource->patient?->uhid,
                'name' => $this->resource->patient?->full_name,
                'age' => $this->resource->patient?->age,
                'sex' => $this->resource->patient?->sex,
                'mobile_no' => $this->resource->patient?->mobile_no,
            ],
            'bill' => [
                'id' => $this->resource->bill?->id,
                'bill_number' => $this->resource->bill?->bill_number,
                'discount' => $this->resource->bill ? (float) $this->resource->bill->discount : 0.0,
                'is_settled' => $this->resource->bill ? (bool) $this->resource->bill->is_settled : false,
                'is_cancelled' => $this->resource->bill ? (bool) $this->resource->bill->is_cancelled : false,
            ],
            'items' => $this->resource->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'service_id' => $item->service_id,
                    'name' => $item->service?->service,
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'tat_due_at' => $item->tat_due_at?->toIso8601String(),
                    'is_outsource' => $item->is_outsource,
                ];
            }),
            'specimens' => $this->resource->specimens->map(function ($specimen) {
                return [
                    'id' => $specimen->id,
                    'barcode' => $specimen->barcode,
                    'type' => $specimen->specimen_type,
                    'container' => $specimen->container,
                    'condition' => $specimen->condition,
                    'collected_at' => $specimen->collected_at?->toIso8601String(),
                    'received_at' => $specimen->received_at?->toIso8601String(),
                ];
            }),
        ];
    }
}
