<?php

declare(strict_types=1);

namespace PHPeste\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use PHPeste\Finance\Enums\ExpenseStatus;

class SaveExpenseRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'finance_category_id' => [$required, 'uuid'],
            'edition_supplier_id' => ['sometimes', 'nullable', 'uuid'],
            'description' => [$required, 'string', 'min:2', 'max:200'],
            'amount_cents' => [$required, 'integer', 'min:1', 'max:100000000000'],
            'due_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'paid_on' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'status' => ['sometimes', Rule::enum(ExpenseStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{finance_category_id?: string, edition_supplier_id?: string|null, description?: string, amount_cents?: int, due_on?: Carbon|null, paid_on?: Carbon|null, status?: ExpenseStatus, notes?: string|null}
     */
    public function expense(): array
    {
        $data = [];

        if ($this->filled('finance_category_id')) {
            $data['finance_category_id'] = $this->string('finance_category_id')->toString();
        }

        if ($this->has('edition_supplier_id')) {
            $data['edition_supplier_id'] = $this->filled('edition_supplier_id') ? $this->string('edition_supplier_id')->toString() : null;
        }

        if ($this->filled('description')) {
            $data['description'] = $this->string('description')->trim()->toString();
        }

        if ($this->filled('amount_cents')) {
            $data['amount_cents'] = $this->integer('amount_cents');
        }

        foreach (['due_on', 'paid_on'] as $key) {
            if ($this->has($key)) {
                $data[$key] = $this->filled($key) ? Date::createFromFormat('Y-m-d', $this->string($key)->toString())?->startOfDay() : null;
            }
        }

        $status = $this->enum('status', ExpenseStatus::class);

        if ($status instanceof ExpenseStatus) {
            $data['status'] = $status;
        }

        if ($this->has('notes')) {
            $data['notes'] = $this->filled('notes') ? $this->string('notes')->toString() : null;
        }

        return $data;
    }
}
