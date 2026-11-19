<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Attachments;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Suppliers\Actions\RecordActivity;
use PHPeste\Suppliers\Enums\ActivityKind;
use PHPeste\Suppliers\Enums\AttachmentKind;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\SupplierAttachment;
use Throwable;

/**
 * Anexos ficam no disco privado e só saem por URL assinada que expira.
 */
class AttachmentStore
{
    public function __construct(private readonly RecordActivity $activity) {}

    public static function disk(): string
    {
        return (string) config('suppliers.attachments.disk', 'local');
    }

    public function store(EditionSupplier $link, AttachmentKind $kind, UploadedFile $file, ?string $actorId): SupplierAttachment
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $kind->extensions(), true)) {
            throw ValidationException::withMessages(['file' => sprintf('Para %s, envie um arquivo %s.', mb_strtolower($kind->label()), implode(', ', $kind->extensions()))]);
        }

        $content = $file->getContent();
        $path = sprintf('suppliers/%s/%s/%s.%s', $link->edition_id, $link->id, Str::uuid7()->toString(), $extension);
        Storage::disk(self::disk())->put($path, $content);

        try {
            $attachment = SupplierAttachment::create([
                'edition_id' => $link->edition_id,
                'edition_supplier_id' => $link->id,
                'kind' => $kind,
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 191),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => mb_strlen($content, '8bit'),
                'uploaded_by' => $actorId,
            ]);
        } catch (Throwable $throwable) {
            Storage::disk(self::disk())->delete($path);

            throw $throwable;
        }

        $this->activity->handle($link, ActivityKind::Attachment, sprintf('%s anexado: %s.', $kind->label(), $attachment->original_name), $actorId);

        return $attachment;
    }

    /**
     * @return array{url: string, expires_at: Carbon}
     */
    public function temporaryUrl(SupplierAttachment $attachment): array
    {
        $expires = now()->addMinutes(max(1, (int) config('suppliers.attachments.url_minutes', 10)));

        return [
            'url' => URL::temporarySignedRoute('suppliers.attachments.download', $expires, ['attachment' => $attachment->id]),
            'expires_at' => $expires,
        ];
    }

    public function delete(SupplierAttachment $attachment): void
    {
        $attachment->delete();
        Storage::disk(self::disk())->delete($attachment->path);
    }
}
