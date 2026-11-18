<?php

declare(strict_types=1);

namespace PHPeste\Branding\Theme;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Community\Contracts\EditionCloner;

/**
 * @phpstan-import-type Theme from ThemeTokens
 */
class ThemeCloner implements EditionCloner
{
    public function key(): string
    {
        return 'identity';
    }

    public function label(): string
    {
        return 'Identidade: tema e logos';
    }

    public function clone(string $sourceEditionId, string $targetEditionId): void
    {
        $source = EditionSetting::query()->forEdition($sourceEditionId)->first();

        if ($source === null) {
            return;
        }

        $copies = [];
        $theme = $this->withCopiedLogos($source->theme_published ?? $source->theme_draft, $targetEditionId, $copies);

        EditionSetting::create([
            'edition_id' => $targetEditionId,
            'theme_draft' => $theme,
            'theme_published' => $source->theme_published === null ? null : $theme,
            'theme_published_at' => null,
        ]);
    }

    /**
     * @param  Theme  $theme
     * @param  array<string, string>  $copies
     * @return Theme
     */
    private function withCopiedLogos(array $theme, string $targetEditionId, array &$copies): array
    {
        $disk = Storage::disk(ThemePresenter::DISK);

        foreach ($theme['logos'] as $slot => $path) {
            if ($path === null || !$disk->exists($path)) {
                $theme['logos'][$slot] = null;

                continue;
            }

            $copies[$path] ??= sprintf('branding/%s/%s-%s.%s', $targetEditionId, $slot, Str::uuid(), pathinfo($path, PATHINFO_EXTENSION));
            $disk->copy($path, $copies[$path]);
            $theme['logos'][$slot] = $copies[$path];
        }

        return $theme;
    }
}
