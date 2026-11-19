<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use PHPeste\Sponsors\Attachments\AttachmentStore;
use PHPeste\Sponsors\Http\People;
use PHPeste\Sponsors\Http\Requests\UploadAttachmentRequest;
use PHPeste\Sponsors\Http\Resources\SponsorAttachmentResource;
use PHPeste\Sponsors\Models\Sponsor;
use PHPeste\Sponsors\Models\SponsorAttachment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SponsorAttachmentController
{
    use Concerns;

    public function store(UploadAttachmentRequest $request, AttachmentStore $store, People $people): JsonResponse
    {
        $this->authorizeSponsors();

        $attachment = $store->store($this->find($request, 'sponsor', Sponsor::class), $request->kind(), $request->upload(), $this->actor($request));
        $people->load([$attachment->uploaded_by]);

        return new SponsorAttachmentResource($attachment)->response()->setStatusCode(201);
    }

    /**
     * URL assinada, válida por poucos minutos, para quem tem permissão agora.
     */
    public function url(Request $request, AttachmentStore $store): JsonResponse
    {
        $this->authorizeSponsors();

        $signed = $store->temporaryUrl($this->find($request, 'attachment', SponsorAttachment::class));

        return response()->json(['data' => ['url' => $signed['url'], 'expires_at' => $signed['expires_at']->clone()->utc()->toIso8601ZuluString()]]);
    }

    public function destroy(Request $request, AttachmentStore $store): Response
    {
        $this->authorizeSponsors();

        $store->delete($this->find($request, 'attachment', SponsorAttachment::class));

        return response()->noContent();
    }

    /**
     * Rota sem login: a assinatura com expiração (middleware `signed`) é a
     * autorização. Sempre como download, nunca exibido no navegador, para um
     * SVG ou PDF não rodar nada no domínio da API.
     */
    public function download(Request $request): StreamedResponse
    {
        $id = $request->route('attachment');
        abort_unless(is_string($id), 404);

        $attachment = SponsorAttachment::query()->withoutGlobalScopes()->whereKey($id)->firstOrFail();
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
