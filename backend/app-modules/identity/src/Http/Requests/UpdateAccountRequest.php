<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use PHPeste\Identity\Enums\NotificationTopic;

class UpdateAccountRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'locale' => ['sometimes', 'required', 'string', 'in:pt_BR,en,es'],
            'notifications' => ['sometimes', 'array:'.implode(',', array_column(NotificationTopic::cases(), 'value'))],
            'notifications.*' => ['boolean'],
        ];
    }
}
