<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Attachments;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Sponsors\Enums\AttachmentKind;
use PHPeste\Sponsors\Models\Sponsor;
use PHPeste\Sponsors\Models\SponsorAttachment;
use Throwable;

/**
 * Anexos ficam num disco privado, fora de qualquer pasta pública. A única
 * forma de baixar é uma URL assinada que expira em poucos minutos, gerada
 * para quem tem permissão no momento do clique.
 */
class AttachmentStore
{
    public static function disk(): string
    {
        return (string) config('sponsors.attachments.disk', 'local');
    }

    public function store(Sponsor $sponsor, AttachmentKind $kind, UploadedFile $file, ?string $actorId): SponsorAttachment
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $kind->extensions(), true)) {
            throw ValidationException::withMessages(['file' => sprintf('Para %s, envie um arquivo %s.', mb_strtolower($kind->label()), implode(', ', $kind->extensions()))]);
        }

        $content = $extension === 'svg' ? $this->sanitizeSvg($file) : $file->getContent();
        $path = sprintf('sponsors/%s/%s/%s.%s', $sponsor->edition_id, $sponsor->id, Str::uuid7()->toString(), $extension);
        Storage::disk(self::disk())->put($path, $content);

        try {
            return DB::transaction(fn (): SponsorAttachment => SponsorAttachment::create([
                'edition_id' => $sponsor->edition_id,
                'sponsor_id' => $sponsor->id,
                'kind' => $kind,
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 191),
                'mime_type' => $extension === 'svg' ? 'image/svg+xml' : $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => mb_strlen($content, '8bit'),
                'uploaded_by' => $actorId,
            ]));
        } catch (Throwable $throwable) {
            Storage::disk(self::disk())->delete($path);

            throw $throwable;
        }
    }

    /**
     * @return array{url: string, expires_at: Carbon}
     */
    public function temporaryUrl(SponsorAttachment $attachment): array
    {
        $expires = now()->addMinutes(max(1, (int) config('sponsors.attachments.url_minutes', 10)));

        return [
            'url' => URL::temporarySignedRoute('sponsors.attachments.download', $expires, ['attachment' => $attachment->id]),
            'expires_at' => $expires,
        ];
    }

    public function delete(SponsorAttachment $attachment): void
    {
        $attachment->delete();
        Storage::disk(self::disk())->delete($attachment->path);
    }

    private function sanitizeSvg(UploadedFile $file): string
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);

        $clean = $sanitizer->sanitize($file->getContent());

        if (!is_string($clean) || mb_trim($clean) === '') {
            throw ValidationException::withMessages(['file' => 'O SVG enviado não é válido.']);
        }

        return $clean;
    }
}
