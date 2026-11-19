<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmailImportedTicketsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'confirm' => ['required', 'boolean'],
            'recipients' => ['required', 'integer', 'min:1'],
        ];
    }
}
