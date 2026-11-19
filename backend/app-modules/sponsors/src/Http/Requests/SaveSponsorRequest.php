<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSponsorRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'min:2', 'max:120'],
            'website' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array{name?: string, website?: string|null, contact_name?: string|null, contact_email?: string|null, contact_phone?: string|null, notes?: string|null}
     */
    public function sponsor(): array
    {
        /** @var array{name?: string, website?: string|null, contact_name?: string|null, contact_email?: string|null, contact_phone?: string|null, notes?: string|null} */
        return $this->safe()->only(['name', 'website', 'contact_name', 'contact_email', 'contact_phone', 'notes']);
    }
}
