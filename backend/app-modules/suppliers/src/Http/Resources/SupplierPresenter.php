<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Resources;

use Illuminate\Support\Carbon;
use PHPeste\Suppliers\Access\SupplierAccess;
use PHPeste\Suppliers\Http\People;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\Supplier;
use PHPeste\Suppliers\Models\SupplierActivity;
use PHPeste\Suppliers\Models\SupplierAttachment;
use PHPeste\Suppliers\Models\SupplierContact;
use PHPeste\Suppliers\Models\SupplierQuote;

/**
 * Monta as respostas do módulo. O contato só entra quando quem vê pode
 * ver (`SupplierAccess::canSeeContacts`); senão vem `contacts: null` e
 * `contacts_visible: false`.
 */
final readonly class SupplierPresenter
{
    public function __construct(
        private SupplierAccess $access,
        private People $people,
    ) {}

    /**
     * @return array{amount: int, currency: string}|null
     */
    public static function money(?int $cents): ?array
    {
        return $cents === null ? null : ['amount' => $cents, 'currency' => 'BRL'];
    }

    /**
     * @return array<string, mixed>
     */
    public function supplier(Supplier $supplier, ?string $viewerId): array
    {
        $visible = $this->access->canSeeContacts($viewerId, $supplier);

        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'service' => $supplier->service->value,
            'service_label' => $supplier->service->label(),
            'description' => $supplier->description,
            'document' => $visible ? $supplier->document : null,
            'website' => $supplier->website,
            'city' => $supplier->city,
            'state' => $supplier->state,
            'contacts_visible' => $visible,
            'contacts' => $visible ? $supplier->contacts->map(fn (SupplierContact $contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'role' => $contact->role,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ])->all() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function link(EditionSupplier $link, ?string $viewerId, bool $detail = false): array
    {
        $best = $link->bestQuoteCents();

        return [
            'id' => $link->id,
            'supplier' => $this->supplier($link->supplier, $viewerId),
            'status' => $link->status->value,
            'status_label' => $link->status->label(),
            'owner' => $this->people->get($link->owner_id),
            'quoted' => self::money($best),
            'contracted' => self::money($link->contracted_cents),
            'accepted_quote_id' => $link->accepted_quote_id,
            'decline_reason' => $link->decline_reason,
            'hired_at' => $this->iso($link->hired_at),
            'declined_at' => $this->iso($link->declined_at),
            'quotes_count' => $link->quotes->count(),
            ...($detail ? [
                'quotes' => $link->quotes->map(fn (SupplierQuote $quote): array => [
                    'id' => $quote->id,
                    'amount' => self::money($quote->amount_cents),
                    'description' => $quote->description,
                    'received_on' => $quote->received_on->toDateString(),
                    'valid_until' => $quote->valid_until?->toDateString(),
                    'expired' => $quote->valid_until !== null && $quote->valid_until->lt(today()),
                    'status' => $quote->status->value,
                    'status_label' => $quote->status->label(),
                    'recorded_by' => $this->people->get($quote->recorded_by),
                ])->all(),
                'attachments' => $link->attachments->map(fn (SupplierAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'kind' => $attachment->kind->value,
                    'kind_label' => $attachment->kind->label(),
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'uploaded_by' => $this->people->get($attachment->uploaded_by),
                    'created_at' => $this->iso($attachment->created_at),
                ])->all(),
                'activities' => $link->activities->map(fn (SupplierActivity $activity): array => [
                    'id' => $activity->id,
                    'kind' => $activity->kind->value,
                    'channel' => $activity->channel?->value,
                    'channel_label' => $activity->channel?->label(),
                    'summary' => $activity->summary,
                    'author' => $this->people->get($activity->user_id),
                    'happened_at' => $this->iso($activity->happened_at),
                ])->all(),
            ] : []),
        ];
    }

    private function iso(?Carbon $date): ?string
    {
        return $date?->clone()->utc()->toIso8601ZuluString();
    }
}
