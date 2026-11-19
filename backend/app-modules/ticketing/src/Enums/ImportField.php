<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Enums;

/**
 * Campo da plataforma que uma coluna do CSV pode alimentar.
 */
enum ImportField: string
{
    case Name = 'name';
    case Email = 'email';
    case TicketType = 'ticket_type';
    case OrderReference = 'order_reference';
    case TicketCode = 'ticket_code';
    case BuyerName = 'buyer_name';
    case BuyerEmail = 'buyer_email';
    case BadgeName = 'badge_name';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Name => 'Nome do participante',
            self::Email => 'E-mail do participante',
            self::TicketType => 'Tipo de ingresso',
            self::OrderReference => 'Número do pedido',
            self::TicketCode => 'Código do ingresso',
            self::BuyerName => 'Nome de quem comprou',
            self::BuyerEmail => 'E-mail de quem comprou',
            self::BadgeName => 'Nome no crachá',
            self::Company => 'Empresa',
        };
    }

    public function required(): bool
    {
        return in_array($this, [self::Name, self::Email], true);
    }

    /**
     * Pedaços de cabeçalho (sem acento, minúsculo) que sugerem este campo.
     *
     * @return list<string>
     */
    public function hints(): array
    {
        return match ($this) {
            self::Name => ['nome do participante', 'nome completo', 'participante', 'nome', 'name'],
            self::Email => ['e-mail do participante', 'email do participante', 'e-mail', 'email'],
            self::TicketType => ['tipo de ingresso', 'tipo do ingresso', 'categoria', 'ingresso', 'ticket type', 'ticket'],
            self::OrderReference => ['numero do pedido', 'n do pedido', 'pedido', 'order'],
            self::TicketCode => ['codigo do ingresso', 'codigo', 'code', 'localizador'],
            self::BuyerName => ['nome do comprador', 'comprador', 'buyer name'],
            self::BuyerEmail => ['e-mail do comprador', 'email do comprador', 'buyer email'],
            self::BadgeName => ['nome no cracha', 'cracha', 'badge'],
            self::Company => ['empresa', 'company', 'organizacao'],
        };
    }
}
