<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

class SaveTaskRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'min:1', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assignee_id' => ['sometimes', 'nullable', 'uuid'],
            'due_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'link_type' => ['sometimes', 'nullable', 'string', 'max:24', 'required_with:link_id'],
            'link_id' => ['sometimes', 'nullable', 'uuid', 'required_with:link_type'],
            'task_column_id' => [$this->isMethod('POST') ? 'sometimes' : 'prohibited', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['task_column_id.prohibited' => 'Para mudar de coluna, mova a tarefa.'];
    }

    /**
     * @return array{title?: string, description?: string|null, assignee_id?: string|null, due_on?: Carbon|null, link_type?: string|null, link_id?: string|null, task_column_id?: string}
     */
    public function task(): array
    {
        $data = [];

        if ($this->filled('title')) {
            $data['title'] = $this->string('title')->trim()->toString();
        }

        foreach (['description', 'assignee_id', 'link_type', 'link_id'] as $key) {
            if ($this->has($key)) {
                $data[$key] = $this->filled($key) ? $this->string($key)->trim()->toString() : null;
            }
        }

        if ($this->has('due_on')) {
            $data['due_on'] = $this->filled('due_on') ? Date::createFromFormat('Y-m-d', $this->string('due_on')->toString())?->startOfDay() : null;
        }

        if ($this->filled('task_column_id')) {
            $data['task_column_id'] = $this->string('task_column_id')->toString();
        }

        return $data;
    }
}
