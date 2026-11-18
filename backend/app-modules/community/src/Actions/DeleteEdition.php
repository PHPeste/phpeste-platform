<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Community\Models\Edition;

class DeleteEdition
{
    public function handle(Edition $edition): void
    {
        if ($edition->status->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'Só é possível remover uma edição em rascunho.',
            ]);
        }

        $edition->delete();
    }
}
