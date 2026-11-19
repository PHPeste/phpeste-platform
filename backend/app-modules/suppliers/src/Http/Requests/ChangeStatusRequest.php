<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Suppliers\Enums\SupplierStatus;

class ChangeStatusRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SupplierStatus::class)],
            'quote_id' => ['sometimes', 'nullable', 'uuid'],
            'contracted_cents' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function status(): SupplierStatus
    {
        $status = $this->enum('status', SupplierStatus::class);
        abort_unless($status instanceof SupplierStatus, 422);

        return $status;
    }
}
