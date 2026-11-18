<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveReviewRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scores' => ['present', 'array'],
            'scores.*' => ['integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:3000'],
            'submit' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function scores(): array
    {
        $scores = [];

        foreach ((array) $this->input('scores', []) as $criterionId => $score) {
            if (is_string($criterionId) && is_numeric($score)) {
                $scores[$criterionId] = (int) $score;
            }
        }

        return $scores;
    }
}
