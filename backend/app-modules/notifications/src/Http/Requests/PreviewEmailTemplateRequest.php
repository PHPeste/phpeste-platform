<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Http\Requests;

use PHPeste\Notifications\Rules\KnownVariables;

class PreviewEmailTemplateRequest extends EmailTemplateRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'required', 'string', 'max:200', new KnownVariables($this->template())],
            'body' => ['sometimes', 'required', 'string', 'max:10000', new KnownVariables($this->template())],
        ];
    }

    /**
     * @param  array{subject: string, body: string}  $saved
     * @return array{subject: string, body: string}
     */
    public function contentOver(array $saved): array
    {
        return [
            'subject' => $this->has('subject') ? $this->string('subject')->toString() : $saved['subject'],
            'body' => $this->has('body') ? $this->string('body')->toString() : $saved['body'],
        ];
    }
}
