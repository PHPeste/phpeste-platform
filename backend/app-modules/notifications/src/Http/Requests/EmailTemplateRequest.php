<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use PHPeste\Notifications\Enums\EmailTemplate;

class EmailTemplateRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    public function template(): EmailTemplate
    {
        $template = $this->route('template');

        return $template instanceof EmailTemplate ? $template : EmailTemplate::from(is_string($template) ? $template : '');
    }
}
