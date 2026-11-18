<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Branding\Enums\LogoSlot;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Branding\Theme\ThemePresenter;

class UploadLogo
{
    public function handle(EditionSetting $settings, LogoSlot $slot, UploadedFile $file): EditionSetting
    {
        $isSvg = mb_strtolower($file->getClientOriginalExtension()) === 'svg' || $file->getMimeType() === 'image/svg+xml';
        $path = sprintf('branding/%s/%s-%s.%s', $settings->edition_id, $slot->value, Str::uuid(), $isSvg ? 'svg' : 'png');

        Storage::disk(ThemePresenter::DISK)->put($path, $isSvg ? $this->sanitizeSvg($file) : $file->getContent());

        $settings->update([
            'theme_draft' => [
                ...$settings->theme_draft,
                'logos' => [...$settings->theme_draft['logos'], $slot->value => $path],
            ],
        ]);

        return $settings;
    }

    private function sanitizeSvg(UploadedFile $file): string
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->removeXMLTag(true);

        $clean = $sanitizer->sanitize($file->getContent());

        if (!is_string($clean) || mb_trim($clean) === '') {
            throw ValidationException::withMessages([
                'file' => 'O SVG enviado não é válido.',
            ]);
        }

        return $clean;
    }
}
