<?php

declare(strict_types=1);

namespace PHPeste\Payments\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Payments\Enums\PaymentMethod;
use PHPeste\Payments\Models\PaymentAccount;

class PaymentAccountResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $account = $this->resource;

        if (!$account instanceof PaymentAccount) {
            return [];
        }

        return [
            'id' => $account->id,
            'driver' => $account->driver,
            'environment' => $account->environment->value,
            'environment_label' => $account->environment->label(),
            'credentials_hint' => '••••'.$account->credentials_hint,
            'holder' => [
                'type' => $account->holder_type->value,
                'type_label' => $account->holder_type->label(),
                'name' => $account->holder_name,
                'document' => $account->holder_document_masked,
            ],
            'methods' => array_map(fn (PaymentMethod $method): array => ['key' => $method->value, 'label' => $method->label()], $account->paymentMethods()),
            'max_installments' => $account->max_installments,
            'pix_expiration_minutes' => $account->pix_expiration_minutes,
            'active' => $account->isActive(),
            'ready' => $account->isReady(),
            'verified_at' => $this->isoDate($account->verified_at),
            'webhook_verified_at' => $this->isoDate($account->webhook_verified_at),
            'webhook_rejections' => ['count' => $account->webhook_rejections, 'last_at' => $this->isoDate($account->webhook_rejected_at)],
            'retired_at' => $this->isoDate($account->retired_at),
            'created_at' => $this->isoDate($account->created_at),
            'webhook_url' => $account->isActive() ? url("/api/v1/webhooks/payments/{$account->webhook_token}") : null,
        ];
    }
}
