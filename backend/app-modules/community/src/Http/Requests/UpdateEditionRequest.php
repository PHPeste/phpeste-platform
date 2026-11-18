<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Community\Enums\BrazilianState;
use PHPeste\Community\Models\Edition;

class UpdateEditionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Edition $edition */
        $edition = $this->route('edition');

        $endsOnRules = ['sometimes', 'nullable', 'date_format:Y-m-d'];
        $startsOnRules = ['sometimes', 'nullable', 'date_format:Y-m-d'];

        if ($this->has('starts_on')) {
            $endsOnRules[] = 'after_or_equal:starts_on';
        } elseif ($edition->starts_on !== null) {
            $endsOnRules[] = 'after_or_equal:'.$edition->starts_on->toDateString();
        }

        if (!$this->has('ends_on') && $edition->ends_on !== null) {
            $startsOnRules[] = 'before_or_equal:'.$edition->ends_on->toDateString();
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('editions', 'slug')->where('community_id', $edition->community_id)->ignore($edition->id),
            ],
            'starts_on' => $startsOnRules,
            'ends_on' => $endsOnRules,
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'state' => ['sometimes', 'nullable', Rule::enum(BrazilianState::class)],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
        ];
    }
}
