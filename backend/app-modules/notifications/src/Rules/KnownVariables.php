<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Mail\TemplateRenderer;

class KnownVariables implements ValidationRule
{
    public function __construct(private readonly EmailTemplate $template) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }

        $unknown = array_diff(TemplateRenderer::placeholders($value), $this->template->variables());

        if ($unknown === []) {
            return;
        }

        $fail(sprintf(
            'Variável desconhecida no modelo: %s. Use só %s.',
            implode(', ', array_map(fn (string $name): string => "{{$name}}", $unknown)),
            implode(', ', array_map(fn (string $name): string => "{{$name}}", $this->template->variables())),
        ));
    }
}
