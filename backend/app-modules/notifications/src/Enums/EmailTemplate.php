<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Enums;

enum EmailTemplate: string
{
    case OrderConfirmed = 'order_confirmed';
    case PixReminder = 'pix_reminder';
    case CfpAccepted = 'cfp_accepted';
    case CfpWaitlisted = 'cfp_waitlisted';
    case EventReminder = 'event_reminder';
    case TeamInvite = 'team_invite';
    case ProposalReceived = 'proposal_received';
    case SpeakerConfirmationReminder = 'speaker_confirmation_reminder';

    public function label(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Confirmação de compra',
            self::PixReminder => 'Lembrete de Pix',
            self::CfpAccepted => 'Resultado do CFP: aprovada',
            self::CfpWaitlisted => 'Resultado do CFP: lista de espera',
            self::EventReminder => 'Antes do evento',
            self::TeamInvite => 'Convite para a equipe',
            self::ProposalReceived => 'Proposta recebida',
            self::SpeakerConfirmationReminder => 'Lembrete de confirmação do palestrante',
        };
    }

    public function trigger(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Ao pagar o pedido',
            self::PixReminder => '30 min antes de expirar',
            self::CfpAccepted, self::CfpWaitlisted => 'Manual',
            self::EventReminder => '7 dias antes',
            self::TeamInvite => 'Ao convidar alguém para a equipe',
            self::ProposalReceived => 'Ao enviar uma proposta',
            self::SpeakerConfirmationReminder => '2 dias antes do prazo de confirmação',
        };
    }

    public function isTransactional(): bool
    {
        return $this !== self::EventReminder;
    }

    /**
     * @return list<string>
     */
    public function variables(): array
    {
        return match ($this) {
            self::OrderConfirmed, self::PixReminder => ['nome', 'edicao', 'pedido', 'data'],
            self::CfpAccepted, self::CfpWaitlisted => ['nome', 'edicao', 'proposta', 'data', 'prazo'],
            self::EventReminder => ['nome', 'edicao', 'data'],
            self::TeamInvite => ['nome', 'edicao', 'papel'],
            self::ProposalReceived => ['nome', 'edicao', 'proposta', 'data'],
            self::SpeakerConfirmationReminder => ['nome', 'edicao', 'proposta', 'prazo'],
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Seu ingresso para o {edicao}',
            self::PixReminder => 'Seu Pix para o {edicao} expira em breve',
            self::CfpAccepted => 'Sua proposta foi aprovada no {edicao}',
            self::CfpWaitlisted => 'Sua proposta está na lista de espera do {edicao}',
            self::EventReminder => 'Falta uma semana para o {edicao}',
            self::TeamInvite => 'Convite para a equipe do {edicao}',
            self::ProposalReceived => 'Recebemos sua proposta para o {edicao}',
            self::SpeakerConfirmationReminder => 'Confirme sua palestra no {edicao} até {prazo}',
        };
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::OrderConfirmed => "Oi, {nome}!\n\nPagamento confirmado. Seu QR code está abaixo, é só mostrar na entrada.",
            self::PixReminder => "Oi, {nome}!\n\nO Pix do pedido {pedido} expira em 30 minutos. Se ainda quiser garantir seu lugar no {edicao}, é só pagar pelo link abaixo.",
            self::CfpAccepted => "Oi, {nome}!\n\nBoa notícia: a proposta \"{proposta}\" foi aprovada para o {edicao}, em {data}. Confirme sua participação pelo link abaixo até {prazo}.",
            self::CfpWaitlisted => "Oi, {nome}!\n\nA proposta \"{proposta}\" ficou na lista de espera do {edicao}. Se abrir uma vaga na grade, avisamos por aqui.",
            self::EventReminder => "Oi, {nome}!\n\nO {edicao} acontece em {data}. Confira a programação e monte sua agenda.",
            self::TeamInvite => "Oi, {nome}!\n\nVocê recebeu um convite para a equipe do {edicao} com o papel de {papel}. Aceite pelo link abaixo.",
            self::ProposalReceived => "Oi, {nome}!\n\nRecebemos a proposta \"{proposta}\" para o {edicao}. Você pode editar ou retirar a proposta até o fechamento do CFP.",
            self::SpeakerConfirmationReminder => "Oi, {nome}!\n\nAinda não recebemos sua confirmação para a palestra \"{proposta}\" no {edicao}. O prazo vai até {prazo}; depois disso a vaga pode ir para a lista de espera.",
        };
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Ver meu ingresso',
            self::PixReminder => 'Pagar com Pix',
            self::CfpAccepted => 'Confirmar participação',
            self::CfpWaitlisted => 'Ver minha proposta',
            self::EventReminder => 'Ver programação',
            self::TeamInvite => 'Aceitar convite',
            self::ProposalReceived => 'Ver minha proposta',
            self::SpeakerConfirmationReminder => 'Confirmar participação',
        };
    }

    /**
     * @return array<string, string>
     */
    public function sampleVariables(): array
    {
        return [
            'nome' => 'Ada Lovelace',
            'pedido' => '#PH-0042',
            'proposta' => 'Laravel no sertão: filas sem drama',
            'papel' => 'organizador',
            'prazo' => '20 de maio',
        ];
    }
}
