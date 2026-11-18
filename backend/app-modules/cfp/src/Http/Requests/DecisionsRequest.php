<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Community\Tenancy\CurrentEdition;

class DecisionsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decisions' => ['present', 'array', 'max:1000'],
            'decisions.*.proposal_id' => ['required', 'uuid', 'distinct', Rule::exists('proposals', 'id')
                ->where('edition_id', resolve(CurrentEdition::class)->id())
                ->where('status', ProposalStatus::Submitted->value)],
            'decisions.*.decision' => ['present', 'nullable', Rule::enum(ProposalDecision::class)],
        ];
    }

    /**
     * @return array<string, ProposalDecision|null>
     */
    public function decisions(): array
    {
        $decisions = [];

        foreach ((array) $this->input('decisions', []) as $item) {
            if (is_array($item) && is_string($item['proposal_id'] ?? null)) {
                $value = $item['decision'] ?? null;
                $decisions[$item['proposal_id']] = is_string($value) ? ProposalDecision::from($value) : null;
            }
        }

        return $decisions;
    }
}
