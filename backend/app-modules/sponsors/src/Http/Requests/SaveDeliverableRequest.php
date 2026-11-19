<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use PHPeste\Sponsors\Enums\DeliverableStatus;

class SaveDeliverableRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'min:2', 'max:200'],
            'owner_id' => ['sometimes', 'nullable', 'uuid'],
            'due_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::enum(DeliverableStatus::class)],
        ];
    }

    /**
     * @return array{title?: string, owner_id?: string|null, due_on?: Carbon|null, status?: DeliverableStatus}
     */
    public function deliverable(): array
    {
        $data = [];

        if ($this->filled('title')) {
            $data['title'] = $this->string('title')->trim()->toString();
        }

        if ($this->has('owner_id')) {
            $data['owner_id'] = $this->filled('owner_id') ? $this->string('owner_id')->toString() : null;
        }

        if ($this->has('due_on')) {
            $data['due_on'] = $this->filled('due_on') ? Date::createFromFormat('Y-m-d', $this->string('due_on')->toString())?->startOfDay() : null;
        }

        $status = $this->enum('status', DeliverableStatus::class);

        if ($status instanceof DeliverableStatus) {
            $data['status'] = $status;
        }

        return $data;
    }
}
