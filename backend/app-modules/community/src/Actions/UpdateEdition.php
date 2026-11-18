<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Community\Models\Edition;

class UpdateEdition
{
    /**
     * @param  array{name?: string, slug?: string, starts_on?: string|null, ends_on?: string|null, city?: string|null, state?: string|null, timezone?: string}  $attributes
     */
    public function handle(Edition $edition, array $attributes): Edition
    {
        $changesSlug = isset($attributes['slug']) && $attributes['slug'] !== $edition->slug;

        if ($changesSlug && $edition->status->isPublished()) {
            throw ValidationException::withMessages([
                'slug' => 'O slug não pode mudar depois que a edição sai de rascunho.',
            ]);
        }

        $edition->update($attributes);

        return $edition;
    }
}
