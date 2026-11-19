<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Sponsors\Http\People;
use PHPeste\Sponsors\Models\SponsorAttachment;

/**
 * Nunca traz o caminho no disco nem um link direto: para baixar, o painel
 * pede `.../attachments/{id}/url` e recebe uma URL assinada que expira.
 */
class SponsorAttachmentResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public static function present(SponsorAttachment $attachment, People $people): array
    {
        return [
            'id' => $attachment->id,
            'kind' => $attachment->kind->value,
            'kind_label' => $attachment->kind->label(),
            'name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'uploaded_by' => $people->get($attachment->uploaded_by),
            'created_at' => $attachment->created_at->clone()->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource instanceof SponsorAttachment ? self::present($this->resource, resolve(People::class)) : [];
    }
}
