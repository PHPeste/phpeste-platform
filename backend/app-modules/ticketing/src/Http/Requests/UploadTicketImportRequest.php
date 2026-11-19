<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadTicketImportRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.(int) config('ticketing.imports.max_kilobytes', 10_240)],
            'platform' => ['required', 'string', 'min:2', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Envie um arquivo CSV (no Excel: Salvar como → CSV).',
        ];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }

    public function platform(): string
    {
        return mb_trim($this->string('platform')->toString());
    }
}
