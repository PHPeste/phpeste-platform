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

    /**
     * @return array{unlocks: list<string>, blocks: list<string>}
     */
    public function effects(): array
    {
        return match ($this) {
            self::Draft => ['unlocks' => [], 'blocks' => []],
            self::CfpOpen => [
                'unlocks' => [
                    'Site e domínios da edição ficam públicos',
                    'Palestrantes podem enviar propostas dentro da janela do CFP',
                    'Tema publicado passa a valer no site e no app',
                ],
                'blocks' => [
                    'O identificador na URL não pode mais mudar',
                    'Não dá para voltar para rascunho',
                ],
            ],
            self::SalesOpen => [
                'unlocks' => [
                    'Site e domínios da edição ficam públicos',
                    'Venda de ingressos, quando houver lotes configurados',
                    'O CFP continua seguindo a própria janela de datas',
                ],
                'blocks' => [
                    'O identificador na URL não pode mais mudar',
                    'Não dá para voltar para CFP aberto nem para rascunho',
                ],
            ],
            self::Ongoing => [
                'unlocks' => [
                    'Check-in dos participantes no evento',
                    'Marcação de presença dos palestrantes',
                ],
                'blocks' => [
                    'Não dá para voltar para vendas abertas',
                ],
            ],
            self::Closed => [
                'unlocks' => [
                    'Relatórios finais da edição',
                ],
                'blocks' => [
                    'A edição fica só para consulta: nenhuma alteração no painel',
                    'Envio de propostas, vendas e check-in encerrados',
                    'Não dá para reabrir a edição',
                ],
            ],
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
