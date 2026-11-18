<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Proposals\CfpWindow;
use PHPeste\Identity\Contracts\UserAccounts;

class AddCoauthor
{
    public const int MAX_COAUTHORS = 3;

    public function __construct(
        private readonly UserAccounts $accounts,
        private readonly CfpWindow $window,
    ) {}

    public function handle(Proposal $proposal, string $email): Proposal
    {
        $this->window->ensureEditable($proposal->edition_id);

        $account = $this->accounts->findByEmail($email);

        if ($account === null) {
            throw ValidationException::withMessages(['email' => 'Não achamos uma conta com este e-mail. Peça para a pessoa criar uma conta antes.']);
        }

        if ($proposal->hasSpeaker($account['id'])) {
            throw ValidationException::withMessages(['email' => 'Esta pessoa já está na proposta.']);
        }

        if ($proposal->speakers->where('is_owner', false)->count() >= self::MAX_COAUTHORS) {
            throw ValidationException::withMessages(['email' => sprintf('Uma proposta pode ter até %d coautores.', self::MAX_COAUTHORS)]);
        }

        $proposal->speakers()->create(['edition_id' => $proposal->edition_id, 'user_id' => $account['id'], 'is_owner' => false]);

        return $proposal->load('speakers');
    }
}
