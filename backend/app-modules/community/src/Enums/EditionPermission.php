<?php

declare(strict_types=1);

namespace PHPeste\Community\Enums;

enum EditionPermission: string
{
    case ManageEdition = 'edition.manage';
    case ManageTeam = 'edition.team';
    case ReviewProposals = 'cfp.review';
    case ManageProgram = 'program.manage';
    case ViewSpeakerPrivateData = 'speakers.private';
    case ViewOrders = 'orders.view';
    case RefundOrders = 'orders.refund';
    case ManageGateway = 'payments.gateway';
    case CheckIn = 'checkin.scan';

    public function label(): string
    {
        return match ($this) {
            self::ManageEdition => 'Configurar edição e identidade',
            self::ManageTeam => 'Gerenciar equipe e convites',
            self::ReviewProposals => 'Avaliar propostas',
            self::ManageProgram => 'Gerenciar palestrantes e grade',
            self::ViewSpeakerPrivateData => 'Ver acessibilidade e alimentação dos palestrantes',
            self::ViewOrders => 'Ver pedidos e pagamentos',
            self::RefundOrders => 'Reembolsar pedidos',
            self::ManageGateway => 'Editar chaves do gateway',
            self::CheckIn => 'Fazer check-in',
        };
    }
}
