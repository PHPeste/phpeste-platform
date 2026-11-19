<?php

declare(strict_types=1);

namespace PHPeste\Payments\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Payments\Data\GatewayCredentials;
use PHPeste\Payments\Data\ReceivingAccount;
use PHPeste\Payments\Enums\GatewayEnvironment;
use PHPeste\Payments\Enums\HolderType;
use PHPeste\Payments\Enums\PaymentMethod;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $driver
 * @property GatewayEnvironment $environment
 * @property array{api_key: string, webhook_secret: string} $credentials
 * @property string $credentials_hint
 * @property HolderType $holder_type
 * @property string $holder_name
 * @property string $holder_document_masked
 * @property list<string> $methods
 * @property int $max_installments
 * @property int $pix_expiration_minutes
 * @property string $webhook_token
 * @property Carbon|null $verified_at
 * @property Carbon|null $webhook_verified_at
 * @property int $webhook_rejections
 * @property Carbon|null $webhook_rejected_at
 * @property Carbon|null $retired_at
 * @property string|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'driver', 'environment', 'credentials', 'credentials_hint', 'holder_type', 'holder_name', 'holder_document_masked', 'methods', 'max_installments', 'pix_expiration_minutes', 'webhook_token', 'verified_at', 'webhook_verified_at', 'retired_at', 'created_by'])]
#[Hidden(['credentials', 'webhook_token'])]
#[Table(name: 'payment_accounts')]
class PaymentAccount extends Model
{
    use BelongsToEdition;
    use HasUuids;

    public function gatewayCredentials(): GatewayCredentials
    {
        return new GatewayCredentials($this->credentials['api_key'], $this->credentials['webhook_secret'], $this->environment);
    }

    public function isActive(): bool
    {
        return $this->retired_at === null;
    }

    public function isReady(): bool
    {
        return $this->isActive() && $this->verified_at !== null && $this->webhook_verified_at !== null;
    }

    /**
     * @return list<PaymentMethod>
     */
    public function paymentMethods(): array
    {
        return array_values(array_filter(array_map(PaymentMethod::tryFrom(...), $this->methods)));
    }

    public function toReceivingAccount(): ReceivingAccount
    {
        return new ReceivingAccount(
            $this->id,
            $this->edition_id,
            $this->driver,
            $this->environment,
            $this->holder_type,
            $this->holder_name,
            $this->paymentMethods(),
            $this->max_installments,
            $this->pix_expiration_minutes,
            $this->isReady(),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'environment' => GatewayEnvironment::class,
            'credentials' => 'encrypted:array',
            'holder_type' => HolderType::class,
            'methods' => 'array',
            'max_installments' => 'integer',
            'pix_expiration_minutes' => 'integer',
            'verified_at' => 'datetime',
            'webhook_verified_at' => 'datetime',
            'webhook_rejections' => 'integer',
            'webhook_rejected_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }
}
