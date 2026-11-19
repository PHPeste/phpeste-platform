<?php

declare(strict_types=1);

namespace PHPeste\Payments\Enums;

enum GatewayCapability: string
{
    case Pix = 'pix';
    case CardToken = 'card_token';
    case HostedCard = 'hosted_card';
    case HostedBoleto = 'hosted_boleto';
}
