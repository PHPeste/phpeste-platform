<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Suppliers\Enums\SupplierService;

class SaveSupplierRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'min:2', 'max:120'],
            'service' => [$creating ? 'required' : 'sometimes', Rule::enum(SupplierService::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'document' => ['sometimes', 'nullable', 'string', 'max:20'],
            'website' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'state' => ['sometimes', 'nullable', 'string', 'size:2'],
            'owner_id' => ['sometimes', 'nullable', 'uuid'],
            'contacts' => [$creating ? 'sometimes' : 'prohibited', 'array', 'max:10'],
            'contacts.*.name' => ['required', 'string', 'min:2', 'max:120'],
            'contacts.*.role' => ['sometimes', 'nullable', 'string', 'max:80'],
            'contacts.*.email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'contacts.*.phone' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array{name?: string, service?: string, description?: string|null, document?: string|null, website?: string|null, city?: string|null, state?: string|null}
     */
    public function supplier(): array
    {
        $data = [];

        foreach (['name', 'service', 'description', 'website', 'city'] as $key) {
            if ($this->has($key)) {
                $data[$key] = $this->filled($key) ? $this->string($key)->trim()->toString() : null;
            }
        }

        if ($this->has('document')) {
            $digits = preg_replace('/\D/', '', $this->string('document')->toString());
            $data['document'] = $digits === '' || $digits === null ? null : $digits;
        }

        if ($this->has('state')) {
            $data['state'] = $this->filled('state') ? $this->string('state')->upper()->toString() : null;
        }

        /** @var array{name?: string, service?: string, description?: string|null, document?: string|null, website?: string|null, city?: string|null, state?: string|null} $data */
        return $data;
    }

    /**
     * @return list<array{name: string, role?: string|null, email?: string|null, phone?: string|null}>
     */
    public function contacts(): array
    {
        $contacts = [];

        foreach ((array) $this->input('contacts', []) as $contact) {
            if (is_array($contact) && is_string($contact['name'] ?? null)) {
                $contacts[] = [
                    'name' => mb_trim($contact['name']),
                    'role' => is_string($contact['role'] ?? null) ? $contact['role'] : null,
                    'email' => is_string($contact['email'] ?? null) ? mb_strtolower($contact['email']) : null,
                    'phone' => is_string($contact['phone'] ?? null) ? $contact['phone'] : null,
                ];
            }
        }

        return $contacts;
    }

    public function ownerId(): ?string
    {
        return $this->filled('owner_id') ? $this->string('owner_id')->toString() : null;
    }
}
