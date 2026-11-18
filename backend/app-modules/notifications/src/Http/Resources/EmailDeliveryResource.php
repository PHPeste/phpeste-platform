<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Notifications\Models\EmailDelivery;

class EmailDeliveryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $delivery = $this->resource;

        if (!$delivery instanceof EmailDelivery) {
            return [];
        }

        return [
            'id' => $delivery->id,
            'template' => $delivery->template->value,
            'to_email' => $delivery->to_email,
            'to_name' => $delivery->to_name,
            'status' => $delivery->status->value,
            'attempts' => $delivery->attempts,
            'last_error' => $delivery->last_error,
            'is_test' => $delivery->is_test,
            'sent_at' => $this->isoDate($delivery->sent_at),
            'created_at' => $this->isoDate($delivery->created_at),
        ];
    }
}
