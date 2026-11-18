<?php

declare(strict_types=1);

namespace PHPeste\Community\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @mixin Model
 */
trait BelongsToEdition
{
    public static function bootBelongsToEdition(): void
    {
        static::addGlobalScope(new EditionScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('edition_id') !== null) {
                return;
            }

            $editionId = resolve(CurrentEdition::class)->id();

            throw_if($editionId === null, LogicException::class, sprintf('%s precisa de edition_id explícito ou de uma edição no contexto.', $model::class));

            $model->setAttribute('edition_id', $editionId);
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    protected function scopeForEdition(Builder $query, string $editionId): void
    {
        $query->withoutGlobalScope(EditionScope::class)->where($this->qualifyColumn('edition_id'), $editionId);
    }
}
