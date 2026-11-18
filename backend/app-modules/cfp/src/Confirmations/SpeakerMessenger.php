<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Confirmations;

use Illuminate\Support\Carbon;
use PHPeste\Branding\Contracts\EditionSites;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\ProposalSpeaker;
use PHPeste\Identity\Contracts\UserAccounts;
use PHPeste\Notifications\Contracts\EditionMailer;
use PHPeste\Notifications\Enums\EmailTemplate;

class SpeakerMessenger
{
    private const array MONTHS = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    public function __construct(
        private readonly UserAccounts $accounts,
        private readonly EditionMailer $mailer,
        private readonly EditionSites $sites,
    ) {}

    public function send(Proposal $proposal, EmailTemplate $template, ?Carbon $deadline = null): void
    {
        $speakerIds = array_values($proposal->speakers->map(fn (ProposalSpeaker $speaker): string => $speaker->user_id)->all());
        $url = $this->sites->url($proposal->edition_id).'/palestrante/propostas/'.$proposal->id;

        foreach ($this->accounts->profiles($speakerIds) as $profile) {
            $this->mailer->send($proposal->edition_id, $template, $profile['email'], $profile['name'], [
                'nome' => $profile['name'],
                'proposta' => $proposal->title,
                'prazo' => $deadline instanceof Carbon ? $deadline->day.' de '.self::MONTHS[$deadline->month] : '',
            ], $url);
        }
    }
}
