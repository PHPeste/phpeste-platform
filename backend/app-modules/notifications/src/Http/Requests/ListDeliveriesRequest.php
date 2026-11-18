<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Notifications\Enums\DeliveryStatus;
use PHPeste\Notifications\Enums\EmailTemplate;

class ListDeliveriesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(DeliveryStatus::class)],
            'template' => ['sometimes', Rule::enum(EmailTemplate::class)],
        ];
    }
}
