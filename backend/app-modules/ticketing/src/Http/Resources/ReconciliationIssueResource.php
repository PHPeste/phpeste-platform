<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Ticketing\Models\ReconciliationIssue;

class ReconciliationIssueResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $issue = $this->resource;

        if (!$issue instanceof ReconciliationIssue) {
            return [];
        }

        return [
            'id' => $issue->id,
            'kind' => $issue->kind->value,
            'label' => $issue->kind->label(),
            'advice' => $issue->kind->advice(),
            'order_id' => $issue->order_id,
            'payment_charge_id' => $issue->payment_charge_id,
            'details' => $issue->details,
            'first_seen_at' => $this->isoDate($issue->first_seen_at),
            'last_seen_at' => $this->isoDate($issue->last_seen_at),
            'resolved' => $issue->resolved_at !== null,
            'resolved_at' => $this->isoDate($issue->resolved_at),
            'resolved_by' => $issue->resolved_by,
            'resolution' => $issue->resolution,
        ];
    }
}
