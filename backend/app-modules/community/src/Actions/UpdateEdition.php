<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Community\Enums\SiteMode;
use PHPeste\Community\Events\EditionSettingsChanged;
use PHPeste\Community\Http\Middleware\AllowEditionSiteOrigins;
use PHPeste\Community\Models\Edition;

class UpdateEdition
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Edition $edition, array $attributes, ?string $actorId = null): Edition
    {
        $changesSlug = isset($attributes['slug']) && $attributes['slug'] !== $edition->slug;

        if ($changesSlug && $edition->status->isPublished()) {
            throw ValidationException::withMessages([
                'slug' => 'O slug não pode mudar depois que a edição sai de rascunho.',
            ]);
        }

        if (isset($attributes['external_site_url']) && is_string($attributes['external_site_url'])) {
            $attributes['external_site_url'] = mb_rtrim($attributes['external_site_url'], '/');
        }

        $mode = isset($attributes['site_mode']) ? SiteMode::from((string) $attributes['site_mode']) : $edition->site_mode;
        $url = array_key_exists('external_site_url', $attributes) ? $attributes['external_site_url'] : $edition->external_site_url;

        if ($mode === SiteMode::External && ($url === null || $url === '')) {
            throw ValidationException::withMessages([
                'external_site_url' => 'Informe o endereço do site da comunidade para usar o site próprio.',
            ]);
        }

        $before = $this->audited($edition);
        $edition->update($attributes);
        $after = $this->audited($edition);

        $changed = array_keys(array_diff_assoc(array_map(fn (mixed $value): string => var_export($value, true), $after), array_map(fn (mixed $value): string => var_export($value, true), $before)));

        if ($changed !== []) {
            AllowEditionSiteOrigins::forget();
            event(new EditionSettingsChanged($edition->id, $actorId, array_intersect_key($before, array_flip($changed)), array_intersect_key($after, array_flip($changed))));
        }

        return $edition;
    }

    /**
     * @return array<string, mixed>
     */
    private function audited(Edition $edition): array
    {
        return [
            'site_mode' => $edition->site_mode->value,
            'external_site_url' => $edition->external_site_url,
            'privacy_contact_email' => $edition->privacy_contact_email,
            'privacy_policy_url' => $edition->privacy_policy_url,
            'anonymize_after_event' => $edition->anonymize_after_event,
        ];
    }
}
