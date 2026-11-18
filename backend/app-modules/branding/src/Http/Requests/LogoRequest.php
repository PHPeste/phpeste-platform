<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use PHPeste\Branding\Enums\LogoSlot;

class LogoRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    public function slot(): LogoSlot
    {
        $slot = $this->route('slot');

        return $slot instanceof LogoSlot ? $slot : LogoSlot::from(is_string($slot) ? $slot : '');
    }
}
