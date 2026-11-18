<?php

declare(strict_types=1);

namespace PHPeste\Community\Enums;

enum EditionStatus: string
{
    case Draft = 'draft';
    case CfpOpen = 'cfp_open';
    case SalesOpen = 'sales_open';
    case Ongoing = 'ongoing';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::CfpOpen => 'CFP aberto',
            self::SalesOpen => 'Vendas abertas',
            self::Ongoing => 'Em andamento',
            self::Closed => 'Encerrada',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::CfpOpen, self::SalesOpen],
            self::CfpOpen => [self::SalesOpen],
            self::SalesOpen => [self::Ongoing],
            self::Ongoing => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isPublished(): bool
    {
        return $this !== self::Draft;
    }
}
