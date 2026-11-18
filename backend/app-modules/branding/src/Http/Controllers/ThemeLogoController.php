<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Controllers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use PHPeste\Branding\Actions\RemoveLogo;
use PHPeste\Branding\Actions\ResolveEditionSettings;
use PHPeste\Branding\Actions\UploadLogo;
use PHPeste\Branding\Http\Requests\LogoRequest;
use PHPeste\Branding\Http\Requests\UploadLogoRequest;
use PHPeste\Branding\Http\Resources\ThemeSettingsResource;

class ThemeLogoController
{
    public function __construct(private readonly ResolveEditionSettings $resolveSettings) {}

    public function store(UploadLogoRequest $request, UploadLogo $uploadLogo): ThemeSettingsResource
    {
        $settings = $this->resolveSettings->handle();

        Gate::authorize('update', $settings);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return new ThemeSettingsResource($uploadLogo->handle($settings, $request->slot(), $file));
    }

    public function destroy(LogoRequest $request, RemoveLogo $removeLogo): ThemeSettingsResource
    {
        $settings = $this->resolveSettings->handle();

        Gate::authorize('update', $settings);

        return new ThemeSettingsResource($removeLogo->handle($settings, $request->slot()));
    }
}
