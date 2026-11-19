<?php

declare(strict_types=1);

namespace PHPeste\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadReceiptRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(int) config('finance.receipts.max_kilobytes', 10_240)],
        ];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
