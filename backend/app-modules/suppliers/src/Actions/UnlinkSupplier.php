<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPeste\Suppliers\Attachments\AttachmentStore;
use PHPeste\Suppliers\Enums\SupplierStatus;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\SupplierAttachment;

/**
 * Tira o fornecedor desta edição (orçamentos, anexos e histórico daqui
 * somem). O cadastro da comunidade continua.
 */
class UnlinkSupplier
{
    public function handle(EditionSupplier $link): void
    {
        if ($link->status === SupplierStatus::Hired) {
            throw ValidationException::withMessages(['supplier' => 'Fornecedor contratado não sai da edição. Recuse antes, com o motivo.']);
        }

        $paths = SupplierAttachment::query()->forEdition($link->edition_id)->where('edition_supplier_id', $link->id)->pluck('path')->all();
        $link->delete();
        Storage::disk(AttachmentStore::disk())->delete($paths);
    }
}
