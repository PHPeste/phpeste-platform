<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Community\Enums\BrazilianState;
use PHPeste\Community\Models\Community;

class StoreEditionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Community $community */
        $community = $this->route('community');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('editions', 'slug')->where('community_id', $community->id)],
            'starts_on' => ['nullable', 'required_with:ends_on', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', Rule::enum(BrazilianState::class)],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
        ];
    }
}
