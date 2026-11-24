<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Suppliers\Enums\QuoteStatus;
use PHPeste\Suppliers\Enums\SupplierStatus;

/**
 * O fornecedor nesta edição: status, responsável, valor contratado.
 *
 * @property string $id
 * @property string $edition_id
 * @property string $supplier_id
 * @property SupplierStatus $status
 * @property string|null $owner_id
 * @property int|null $contracted_cents
 * @property string|null $accepted_quote_id
 * @property string|null $decline_reason
 * @property Carbon|null $hired_at
 * @property Carbon|null $declined_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Supplier $supplier
 * @property-read Collection<int, SupplierQuote> $quotes
 * @property-read Collection<int, SupplierAttachment> $attachments
 * @property-read Collection<int, SupplierActivity> $activities
 */
#[Fillable(['edition_id', 'supplier_id', 'status', 'owner_id', 'contracted_cents', 'accepted_quote_id', 'decline_reason', 'hired_at', 'declined_at'])]
#[Table(name: 'edition_suppliers')]
class EditionSupplier extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<SupplierQuote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(SupplierQuote::class)->latest('received_on')->orderByDesc('id');
    }

    /**
     * @return HasMany<SupplierAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupplierAttachment::class)->latest()->orderByDesc('id');
    }

    /**
     * @return HasMany<SupplierActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(SupplierActivity::class)->latest('happened_at')->orderByDesc('id');
    }

    /**
     * O "orçado" da lista e dos totais: o orçamento aceito no contrato ou,
     * sem ele, o menor que ainda vale.
     */
    public function bestQuoteCents(): ?int
    {
        $accepted = $this->quotes->first(fn (SupplierQuote $quote): bool => $quote->status === QuoteStatus::Accepted);

        if ($accepted instanceof SupplierQuote) {
            return $accepted->amount_cents;
        }

        $amounts = $this->quotes->filter(fn (SupplierQuote $quote): bool => $quote->status !== QuoteStatus::Rejected)->pluck('amount_cents');

        return $amounts->isEmpty() ? null : (int) $amounts->min();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
            'contracted_cents' => 'integer',
            'hired_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }
}
