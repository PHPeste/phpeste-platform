<?php

declare(strict_types=1);

namespace PHPeste\Payments\Gateway;

use RuntimeException;
use Throwable;

class GatewayRequestFailed extends RuntimeException
{
    public const string UNAVAILABLE = 'unavailable';

    public const string INVALID_CREDENTIALS = 'invalid_credentials';

    public const string INVALID_PAYER = 'invalid_payer';

    public const string DECLINED = 'declined';

    public const string NOT_FOUND = 'not_found';

    public const string REJECTED = 'rejected';

    public function __construct(
        string $message,
        public readonly string $reason = self::REJECTED,
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function publicMessage(): string
    {
        return match ($this->reason) {
            self::UNAVAILABLE => 'O meio de pagamento está fora do ar agora. Tente de novo em alguns minutos; sua reserva continua valendo.',
            self::INVALID_CREDENTIALS => 'Os pagamentos desta edição estão temporariamente indisponíveis. A organização já pode ver o problema no painel.',
            self::INVALID_PAYER => 'O meio de pagamento não aceitou os dados de quem paga. Confira o nome, o e-mail e o CPF.',
            self::DECLINED => 'O pagamento foi recusado. Tente outro cartão ou pague com Pix.',
            default => 'Não foi possível iniciar o pagamento. Tente de novo ou escolha outra forma de pagamento.',
        };
    }
}
