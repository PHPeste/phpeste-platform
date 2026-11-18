<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PHPeste\Branding\Actions\ApplyThemePreset;
use PHPeste\Branding\Actions\DiscardThemeDraft;
use PHPeste\Branding\Actions\PublishTheme;
use PHPeste\Branding\Actions\ResolveEditionSettings;
use PHPeste\Branding\Actions\RevertTheme;
use PHPeste\Branding\Actions\UpdateThemeDraft;
use PHPeste\Branding\Enums\ThemePreset;
use PHPeste\Branding\Http\Requests\ApplyThemePresetRequest;
use PHPeste\Branding\Http\Requests\UpdateThemeDraftRequest;
use PHPeste\Branding\Http\Resources\ThemeSettingsResource;

class ThemeController
{
    public function __construct(private readonly ResolveEditionSettings $resolveSettings) {}

    public function show(): ThemeSettingsResource
    {
        $settings = $this->resolveSettings->handle();

        Gate::authorize('view', $settings);

        return new ThemeSettingsResource($settings);
    }

    public function updateDraft(UpdateThemeDraftRequest $request, UpdateThemeDraft $updateDraft): ThemeSettingsResource
    {
        $settings = $this->resolveSettings->handle();

        Gate::authorize('update', $settings);

        return new ThemeSettingsResource($updateDraft->handle(
            $settings,
            $request->colors(),
            $request->fonts(),
            $request->integer('radius'),
        ));
    }

    public function applyPreset(ApplyThemePresetRequest $request, ApplyThemePreset $applyPreset): ThemeSettingsResource
    {
        $settings = $this->resolveSettings->handle();

        Gate::authorize('update', $settings);

        return new ThemeSettingsResource($applyPreset->handle($settings, ThemePreset::from($request->string('preset')->toString())));
    }

    public function discardDraft(DiscardThemeDraft $discardDraft): ThemeSettingsResource
    {
        $settings = $this->resolveSettings->handle();

        Gate::authorize('update', $settings);

        return new ThemeSettingsResource($discardDraft->handle($settings));
    }

    public function publish(Request $request, PublishTheme $publishTheme): ThemeSettingsResource
    {
        $settings = $this->resolveSettings->handle();

        Gate::authorize('update', $settings);

        return new ThemeSettingsResource($publishTheme->handle($settings, (string) $request->user()?->getAuthIdentifier()));
    }

    public function revert(Request $request, RevertTheme $revertTheme): ThemeSettingsResource
    {
        $settings = $this->resolveSettings->handle();

        Gate::authorize('update', $settings);

        return new ThemeSettingsResource($revertTheme->handle($settings, (string) $request->user()?->getAuthIdentifier()));
    }
}
