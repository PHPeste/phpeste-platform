<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Data;

final readonly class OrderRequest
{
    /**
     * @param  list<array{ticket_type_id: string, attendees: list<OrderAttendee>}>  $items
     */
    public function __construct(
        public string $buyerName,
        public string $buyerEmail,
        public ?string $buyerDocument,
        public array $items,
        public ?string $couponCode,
        public ?string $accessCode,
        public ?string $userId,
    ) {}

    public function hash(): string
    {
        return hash('sha256', (string) json_encode([
            $this->buyerName,
            $this->buyerEmail,
            $this->buyerDocument,
            array_map(fn (array $item): array => [$item['ticket_type_id'], array_map(fn (OrderAttendee $attendee): array => [$attendee->name, $attendee->email, $attendee->answers], $item['attendees'])], $this->items),
            $this->couponCode,
            $this->accessCode,
        ]));
    }

    public function quantity(): int
    {
        return array_sum(array_map(fn (array $item): int => count($item['attendees']), $this->items));
    }
}
