<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use PHPeste\Suppliers\Enums\AttachmentKind;

class UploadAttachmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(AttachmentKind::class)],
            'file' => ['required', 'file', 'max:'.(int) config('suppliers.attachments.max_kilobytes', 20_480)],
        ];
    }

    public function kind(): AttachmentKind
    {
        $kind = $this->enum('kind', AttachmentKind::class);
        abort_unless($kind instanceof AttachmentKind, 422);

        return $kind;
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
