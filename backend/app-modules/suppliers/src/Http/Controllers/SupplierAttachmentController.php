<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use PHPeste\Suppliers\Attachments\AttachmentStore;
use PHPeste\Suppliers\Http\Requests\UploadAttachmentRequest;
use PHPeste\Suppliers\Models\SupplierAttachment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierAttachmentController
{
    use Concerns;

    public function store(UploadAttachmentRequest $request, AttachmentStore $store): JsonResponse
    {
        $this->authorizeSuppliers();

        $attachment = $store->store($this->link($request), $request->kind(), $request->upload(), $this->actor($request));

        return response()->json(['data' => [
            'id' => $attachment->id,
            'kind' => $attachment->kind->value,
            'name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
        ]], 201);
    }

    public function url(Request $request, AttachmentStore $store): JsonResponse
    {
        $this->authorizeSuppliers();

        $signed = $store->temporaryUrl($this->find($request, 'attachment', SupplierAttachment::class));

        return response()->json(['data' => ['url' => $signed['url'], 'expires_at' => $signed['expires_at']->clone()->utc()->toIso8601ZuluString()]]);
    }

    public function destroy(Request $request, AttachmentStore $store): Response
    {
        $this->authorizeSuppliers();

        $store->delete($this->find($request, 'attachment', SupplierAttachment::class));

        return response()->noContent();
    }

    /**
     * Sem login: a assinatura com expiração é a autorização. Sempre como
     * download, com `nosniff` e CSP `sandbox`.
     */
    public function download(Request $request): StreamedResponse
    {
        $id = $request->route('attachment');
        abort_unless(is_string($id), 404);

        $attachment = SupplierAttachment::query()->withoutGlobalScopes()->whereKey($id)->firstOrFail();
        $disk = Storage::disk(AttachmentStore::disk());
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
