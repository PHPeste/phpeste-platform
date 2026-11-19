<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferTicketRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }
}
