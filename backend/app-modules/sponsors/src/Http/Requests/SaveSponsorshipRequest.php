<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Sponsors\Enums\SponsorshipStage;

class SaveSponsorshipRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sponsor_tier_id' => ['sometimes', 'nullable', 'uuid'],
            'value_cents' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'owner_id' => ['sometimes', 'nullable', 'uuid'],
            'stage' => ['sometimes', Rule::enum(SponsorshipStage::class)],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{sponsor_tier_id?: string|null, value_cents?: int|null, owner_id?: string|null, stage?: SponsorshipStage}
     */
    public function sponsorship(): array
    {
        $data = [];

        foreach (['sponsor_tier_id', 'owner_id'] as $key) {
            if ($this->has($key)) {
                $data[$key] = $this->filled($key) ? $this->string($key)->toString() : null;
            }
        }

        if ($this->has('value_cents')) {
            $data['value_cents'] = $this->filled('value_cents') ? $this->integer('value_cents') : null;
        }

        $stage = $this->enum('stage', SponsorshipStage::class);

        if ($stage instanceof SponsorshipStage) {
            $data['stage'] = $stage;
        }

        return $data;
    }
}
