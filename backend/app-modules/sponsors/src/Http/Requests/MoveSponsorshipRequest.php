<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Sponsors\Enums\SponsorshipStage;

class MoveSponsorshipRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(SponsorshipStage::class)],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
            'lost_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    public function stage(): SponsorshipStage
    {
        $stage = $this->enum('stage', SponsorshipStage::class);
        abort_unless($stage instanceof SponsorshipStage, 422);

        return $stage;
    }
}
