<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Http\Requests;

use PHPeste\Notifications\Rules\KnownVariables;

class SaveEmailTemplateRequest extends EmailTemplateRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:200', new KnownVariables($this->template())],
            'body' => ['required', 'string', 'max:10000', new KnownVariables($this->template())],
        ];
    }
}
