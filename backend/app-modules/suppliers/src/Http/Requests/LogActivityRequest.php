<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Suppliers\Enums\ContactChannel;

class LogActivityRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(ContactChannel::class)],
            'happened_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:now'],
            'summary' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }

    public function channel(): ContactChannel
    {
        $channel = $this->enum('channel', ContactChannel::class);
        abort_unless($channel instanceof ContactChannel, 422);

        return $channel;
    }
}
