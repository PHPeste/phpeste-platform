<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Cfp\Data\ProposalForm;
use PHPeste\Cfp\Enums\ProposalFormat;
use PHPeste\Community\Tenancy\CurrentEdition;

class InviteSpeakerRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'title' => ['required', 'string', 'max:'.ProposalForm::TITLE_MAX],
            'track_id' => ['required', 'uuid', Rule::exists('cfp_tracks', 'id')->where('edition_id', resolve(CurrentEdition::class)->id())],
            'format' => ['required', Rule::enum(ProposalFormat::class)],
            'summary' => ['sometimes', 'nullable', 'string', 'max:'.ProposalForm::SUMMARY_MAX],
        ];
    }

    /**
     * @return array{name: string, email: string, title: string, track_id: string, format: string, summary: string|null}
     */
    public function invitation(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->string('email')->trim()->lower()->toString(),
            'title' => $this->string('title')->trim()->toString(),
            'track_id' => $this->string('track_id')->toString(),
            'format' => $this->string('format')->toString(),
            'summary' => $this->filled('summary') ? $this->string('summary')->trim()->toString() : null,
        ];
    }
}
