<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Payments\Data\RefundRecord;

class RefundResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $refund = $this->resource;

        if (!$refund instanceof RefundRecord) {
            return [];
        }

        $itemIds = $refund->metadata['item_ids'] ?? [];

        return [
            'id' => $refund->id,
            'order_id' => $refund->reference,
            'amount' => $this->money($refund->amountCents),
            'status' => $refund->status->value,
            'status_label' => $refund->status->label(),
            'reason' => $refund->reason->value,
            'reason_label' => $refund->reason->label(),
            'note' => $refund->note,
            'cancelled_item_ids' => is_array($itemIds) ? array_values($itemIds) : [],
            'failure_reason' => $refund->failureReason,
            'requested_by' => $refund->requestedBy,
            'created_at' => $this->isoDate($refund->createdAt),
            'completed_at' => $this->isoDate($refund->completedAt),
        ];
    }
}
