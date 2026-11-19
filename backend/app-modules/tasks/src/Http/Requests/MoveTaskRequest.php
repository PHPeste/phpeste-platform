<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveTaskRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'task_column_id' => ['required', 'uuid'],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
