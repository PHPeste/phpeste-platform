<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use PHPeste\Cfp\Models\CfpSetting;

class UpdateCfpSettingsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'opens_at' => ['sometimes', 'nullable', 'date'],
            'closes_at' => ['sometimes', 'nullable', 'date'],
            'results_at' => ['sometimes', 'nullable', 'date'],
            'anonymous_review' => ['sometimes', 'boolean'],
            'require_nordeste_quota' => ['sometimes', 'boolean'],
            'nordeste_quota_percent' => ['sometimes', 'integer', 'between:0,100'],
            'proposals_per_person' => ['sometimes', 'integer', 'between:1,20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function windowErrors(CfpSetting $current): array
    {
        $opens = $this->dateOr('opens_at', $current->opens_at);
        $closes = $this->dateOr('closes_at', $current->closes_at);
        $results = $this->dateOr('results_at', $current->results_at);
        $errors = [];

        if ($opens instanceof Carbon && $closes instanceof Carbon && $closes->lte($opens)) {
            $errors['closes_at'] = 'O fechamento precisa ser depois da abertura.';
        }

        if ($closes instanceof Carbon && $results instanceof Carbon && $results->lt($closes)) {
            $errors['results_at'] = 'O resultado precisa sair depois do fechamento.';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $values = $this->validated();

        foreach (['opens_at', 'closes_at', 'results_at'] as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = $this->dateOr($field, null)?->utc();
            }
        }

        return $values;
    }

    private function dateOr(string $field, ?Carbon $fallback): ?Carbon
    {
        if (!$this->has($field)) {
            return $fallback;
        }

        $value = $this->input($field);

        return is_string($value) && $value !== '' ? Date::parse($value) : null;
    }
}
