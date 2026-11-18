<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Requests;

class UploadLogoRequest extends LogoRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => $this->slot()->rules(),
        ];
    }
}
