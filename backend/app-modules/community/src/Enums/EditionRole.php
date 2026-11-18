<?php

declare(strict_types=1);

namespace PHPeste\Community\Enums;

enum EditionRole: string
{
    case Organizer = 'organizador';
    case Finance = 'financeiro';
    case Reviewer = 'avaliador';
    case CheckIn = 'checkin';
    case Volunteer = 'voluntario';

    public function label(): string
    {
        return match ($this) {
            self::Organizer => 'Organizador',
            self::Finance => 'Financeiro',
            self::Reviewer => 'Revisor do CFP',
            self::CheckIn => 'Credenciamento',
            self::Volunteer => 'Voluntário',
        };
    }

    public function access(): string
    {
        return match ($this) {
            self::Organizer => 'Tudo na edição',
            self::Finance => 'Pedidos, pagamentos e finanças',
            self::Reviewer => 'CFP e palestrantes',
            self::CheckIn => 'Check-in',
            self::Volunteer => 'Visão geral da edição',
        };
    }

    /**
     * @return list<EditionPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Organizer => EditionPermission::cases(),
            self::Finance => [EditionPermission::ViewOrders],
            self::Reviewer => [EditionPermission::ReviewProposals],
            self::CheckIn => [EditionPermission::CheckIn],
            self::Volunteer => [],
        };
    }

    public function can(EditionPermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
