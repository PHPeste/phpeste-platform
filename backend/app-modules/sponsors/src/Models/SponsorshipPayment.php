<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * Pagamento recebido de um patrocinador, registrado à mão (a plataforma não
 * recebe dinheiro, ADR 0009).
 *
 * @property string $id
 * @property string $edition_id
 * @property string $sponsorship_id
 * @property int $amount_cents
 * @property Carbon $paid_on
 * @property string|null $note
 * @property string|null $recorded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'sponsorship_id', 'amount_cents', 'paid_on', 'note', 'recorded_by'])]
#[Table(name: 'sponsorship_payments')]
class SponsorshipPayment extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'paid_on' => 'date'];
    }
}
