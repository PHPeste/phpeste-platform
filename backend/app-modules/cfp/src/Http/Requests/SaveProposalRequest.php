<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Cfp\Enums\ProposalFormat;
use PHPeste\Cfp\Enums\ProposalLanguage;
use PHPeste\Cfp\Enums\ProposalLevel;
use PHPeste\Community\Tenancy\CurrentEdition;

class SaveProposalRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:90'],
            'track_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('cfp_tracks', 'id')->where('edition_id', resolve(CurrentEdition::class)->id())],
            'summary' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'audience' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'format' => ['sometimes', 'nullable', Rule::enum(ProposalFormat::class)],
            'level' => ['sometimes', 'nullable', Rule::enum(ProposalLevel::class)],
            'language' => ['sometimes', Rule::enum(ProposalLanguage::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function proposal(): array
    {
        return $this->validated();
    }
}
