<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtendDeadlineRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['deadline_at' => ['required', 'date', 'after:now']];
    }
}
