<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Community\Jobs\CloneEdition;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionClone;

class RequestEditionClone
{
    /**
     * @param  list<string>  $parts
     */
    public function handle(Edition $source, string $name, string $slug, array $parts, string $requestedBy): EditionClone
    {
        $pending = EditionClone::query()
            ->where('source_edition_id', $source->id)
            ->where('slug', $slug)
            ->where('status', EditionClone::QUEUED)
            ->exists();

        if ($pending || $source->community->editions()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => 'Já existe uma edição com este slug nesta comunidade.']);
        }

        $clone = EditionClone::create([
            'source_edition_id' => $source->id,
            'requested_by' => $requestedBy,
            'name' => $name,
            'slug' => $slug,
            'parts' => array_values(array_unique($parts)),
            'status' => EditionClone::QUEUED,
        ]);

        dispatch(new CloneEdition($source->id, $clone->id));

        return $clone->refresh();
    }
}
