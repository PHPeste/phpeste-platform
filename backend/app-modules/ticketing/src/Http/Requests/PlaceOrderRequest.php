<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use PHPeste\Ticketing\Data\OrderAttendee;
use PHPeste\Ticketing\Data\OrderRequest;
use PHPeste\Ticketing\Rules\Cpf;

class PlaceOrderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'buyer.name' => ['required', 'string', 'min:2', 'max:120'],
            'buyer.email' => ['required', 'email:rfc', 'max:255'],
            'buyer.document' => ['nullable', 'string', new Cpf],
            'coupon' => ['nullable', 'string', 'max:40'],
            'access_code' => ['nullable', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.ticket_type_id' => ['required', 'uuid', 'distinct'],
            'items.*.attendees' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.attendees.*.name' => ['required', 'string', 'min:2', 'max:120'],
            'items.*.attendees.*.email' => ['required', 'email:rfc', 'max:255'],
            'items.*.attendees.*.answers' => ['sometimes', 'array'],
        ];
    }

    public function order(?string $userId): OrderRequest
    {
        $items = [];

        foreach ((array) $this->validated('items') as $item) {
            if (!is_array($item) || !is_string($item['ticket_type_id'] ?? null)) {
                continue;
            }

            $attendees = [];

            foreach ((array) ($item['attendees'] ?? []) as $attendee) {
                if (is_array($attendee)) {
                    /** @var array<string, mixed> $answers */
                    $answers = is_array($attendee['answers'] ?? null) ? $attendee['answers'] : [];
                    $attendees[] = new OrderAttendee($this->text($attendee['name'] ?? ''), $this->email($attendee['email'] ?? ''), $answers);
                }
            }

            $items[] = ['ticket_type_id' => $item['ticket_type_id'], 'attendees' => $attendees];
        }

        $document = $this->validated('buyer.document');
        $coupon = $this->validated('coupon');
        $code = $this->validated('access_code');

        return new OrderRequest(
            buyerName: $this->text($this->validated('buyer.name')),
            buyerEmail: $this->email($this->validated('buyer.email')),
            buyerDocument: is_string($document) && $document !== '' ? Cpf::digits($document) : null,
            items: $items,
            couponCode: is_string($coupon) && mb_trim($coupon) !== '' ? $coupon : null,
            accessCode: is_string($code) && mb_trim($code) !== '' ? $code : null,
            userId: $userId,
        );
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? mb_trim($value) : '';
    }

    private function email(mixed $value): string
    {
        return mb_strtolower($this->text($value));
    }
}
