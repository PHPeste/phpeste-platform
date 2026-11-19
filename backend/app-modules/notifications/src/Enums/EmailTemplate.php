<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Enums;

enum EmailTemplate: string
{
    case OrderConfirmed = 'order_confirmed';
    case PixReminder = 'pix_reminder';
    case OrderRefunded = 'order_refunded';
    case TicketTransferred = 'ticket_transferred';
    case CfpAccepted = 'cfp_accepted';
    case CfpWaitlisted = 'cfp_waitlisted';
    case EventReminder = 'event_reminder';
    case TeamInvite = 'team_invite';
    case ProposalReceived = 'proposal_received';
    case SpeakerConfirmationReminder = 'speaker_confirmation_reminder';
    case SpeakerInvited = 'speaker_invited';
    case TaskAssigned = 'task_assigned';
    case TaskDueSoon = 'task_due_soon';

    public function label(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Confirmação de compra',
            self::PixReminder => 'Lembrete de pagamento',
            self::OrderRefunded => 'Reembolso do pedido',
            self::TicketTransferred => 'Ingresso transferido',
            self::CfpAccepted => 'Resultado do CFP: aprovada',
            self::CfpWaitlisted => 'Resultado do CFP: lista de espera',
            self::EventReminder => 'Antes do evento',
            self::TeamInvite => 'Convite para a equipe',
            self::ProposalReceived => 'Proposta recebida',
            self::SpeakerConfirmationReminder => 'Lembrete de confirmação do palestrante',
            self::SpeakerInvited => 'Convite para palestrar',
            self::TaskAssigned => 'Tarefa atribuída',
            self::TaskDueSoon => 'Prazo de tarefa chegando',
        };
    }

    public function trigger(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Ao pagar o pedido',
            self::PixReminder => 'Quando a organização reenvia a cobrança',
            self::OrderRefunded => 'Ao reembolsar o pedido',
            self::TicketTransferred => 'Ao receber um ingresso transferido',
            self::CfpAccepted, self::CfpWaitlisted => 'Manual',
            self::EventReminder => '7 dias antes',
            self::TeamInvite => 'Ao convidar alguém para a equipe',
            self::ProposalReceived => 'Ao enviar uma proposta',
            self::SpeakerConfirmationReminder => '2 dias antes do prazo de confirmação',
            self::SpeakerInvited => 'Ao convidar alguém direto para palestrar',
            self::TaskAssigned => 'Ao virar responsável por uma tarefa',
            self::TaskDueSoon => '1 dia antes do prazo da tarefa',
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
            self::OrderRefunded => ['nome', 'edicao', 'pedido', 'valor', 'ingressos'],
            self::TicketTransferred => ['nome', 'edicao', 'data', 'remetente', 'ingresso'],
            self::CfpAccepted, self::CfpWaitlisted => ['nome', 'edicao', 'proposta', 'data', 'prazo'],
            self::EventReminder => ['nome', 'edicao', 'data'],
            self::TeamInvite => ['nome', 'edicao', 'papel'],
            self::ProposalReceived => ['nome', 'edicao', 'proposta', 'data'],
            self::SpeakerConfirmationReminder, self::SpeakerInvited => ['nome', 'edicao', 'proposta', 'prazo'],
            self::TaskAssigned, self::TaskDueSoon => ['nome', 'edicao', 'tarefa', 'prazo'],
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Seu ingresso para o {edicao}',
            self::PixReminder => 'Seu pedido no {edicao} está esperando pagamento',
            self::OrderRefunded => 'Reembolso do seu pedido no {edicao}',
            self::TicketTransferred => '{remetente} transferiu um ingresso do {edicao} para você',
            self::CfpAccepted => 'Sua proposta foi aprovada no {edicao}',
            self::CfpWaitlisted => 'Sua proposta está na lista de espera do {edicao}',
            self::EventReminder => 'Falta uma semana para o {edicao}',
            self::TeamInvite => 'Convite para a equipe do {edicao}',
            self::ProposalReceived => 'Recebemos sua proposta para o {edicao}',
            self::SpeakerConfirmationReminder => 'Confirme sua palestra no {edicao} até {prazo}',
            self::SpeakerInvited => 'Convite para palestrar no {edicao}',
            self::TaskAssigned => 'Nova tarefa para você no {edicao}: {tarefa}',
            self::TaskDueSoon => 'O prazo de "{tarefa}" é {prazo}',
        };
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::OrderConfirmed => "Oi, {nome}!\n\nSeu ingresso para o {edicao} está garantido (pedido {pedido}). Entre com este e-mail para ver o QR code, completar os dados do crachá e, se precisar, transferir o ingresso para outra pessoa. É só mostrar o QR na entrada.",
            self::PixReminder => "Oi, {nome}!\n\nO pedido {pedido} ainda não foi pago. Se ainda quiser garantir seu lugar no {edicao}, é só pagar pelo link abaixo antes de a reserva expirar.",
            self::OrderRefunded => "Oi, {nome}!\n\nFizemos o reembolso de {valor} do pedido {pedido} no {edicao}. {ingressos}\n\nO valor volta pelo mesmo meio de pagamento: no Pix, na conta de origem; no cartão, pode levar até duas faturas para aparecer.",
            self::TicketTransferred => "Oi, {nome}!\n\n{remetente} transferiu para você o ingresso {ingresso} do {edicao}, que acontece em {data}. Entre com este e-mail para ver o QR code e completar seus dados de crachá.",
            self::CfpAccepted => "Oi, {nome}!\n\nBoa notícia: a proposta \"{proposta}\" foi aprovada para o {edicao}, em {data}. Confirme sua participação pelo link abaixo até {prazo}.",
            self::CfpWaitlisted => "Oi, {nome}!\n\nA proposta \"{proposta}\" ficou na lista de espera do {edicao}. Se abrir uma vaga na grade, avisamos por aqui.",
            self::EventReminder => "Oi, {nome}!\n\nO {edicao} acontece em {data}. Confira a programação e monte sua agenda.",
            self::TeamInvite => "Oi, {nome}!\n\nVocê recebeu um convite para a equipe do {edicao} com o papel de {papel}. Aceite pelo link abaixo.",
            self::ProposalReceived => "Oi, {nome}!\n\nRecebemos a proposta \"{proposta}\" para o {edicao}. Você pode editar ou retirar a proposta até o fechamento do CFP.",
            self::SpeakerConfirmationReminder => "Oi, {nome}!\n\nAinda não recebemos sua confirmação para a palestra \"{proposta}\" no {edicao}. O prazo vai até {prazo}; depois disso a vaga pode ir para a lista de espera.",
            self::SpeakerInvited => "Oi, {nome}!\n\nA organização do {edicao} convidou você para apresentar \"{proposta}\". Confirme sua participação pelo link abaixo até {prazo}.\n\nPara entrar, use este e-mail: se ainda não tem senha na plataforma, entre pelo GitHub ou peça uma senha em \"Esqueci minha senha\".",
            self::TaskAssigned => "Oi, {nome}!\n\nVocê é responsável pela tarefa \"{tarefa}\" no {edicao}. Prazo: {prazo}.",
            self::TaskDueSoon => "Oi, {nome}!\n\nA tarefa \"{tarefa}\" do {edicao}, que está com você, vence {prazo}. Se já terminou, mova para a coluna de concluídas no painel."
        };
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Ver meu ingresso',
            self::PixReminder => 'Pagar o pedido',
            self::OrderRefunded => 'Ver meu pedido',
            self::TicketTransferred => 'Ver meu ingresso',
            self::CfpAccepted => 'Confirmar participação',
            self::CfpWaitlisted => 'Ver minha proposta',
            self::EventReminder => 'Ver programação',
            self::TeamInvite => 'Aceitar convite',
            self::ProposalReceived => 'Ver minha proposta',
            self::SpeakerConfirmationReminder, self::SpeakerInvited => 'Confirmar participação',
            self::TaskAssigned, self::TaskDueSoon => 'Ver tarefa',
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
            'valor' => 'R$ 150,00',
            'ingressos' => 'Seus ingressos continuam valendo.',
            'remetente' => 'Grace Hopper',
            'ingresso' => 'K7QM-4XTD',
            'proposta' => 'Laravel no sertão: filas sem drama',
            'papel' => 'organizador',
            'prazo' => '20 de maio',
            'tarefa' => 'Fechar o buffet do coffee break',
        ];
    }
}
