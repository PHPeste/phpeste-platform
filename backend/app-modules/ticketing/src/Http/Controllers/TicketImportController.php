<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Actions\DiscardTicketImport;
use PHPeste\Ticketing\Actions\EmailImportedTickets;
use PHPeste\Ticketing\Actions\MapTicketImport;
use PHPeste\Ticketing\Actions\ResumeTicketImport;
use PHPeste\Ticketing\Actions\StartTicketImport;
use PHPeste\Ticketing\Actions\UploadTicketImport;
use PHPeste\Ticketing\Http\Requests\EmailImportedTicketsRequest;
use PHPeste\Ticketing\Http\Requests\MapTicketImportRequest;
use PHPeste\Ticketing\Http\Requests\UploadTicketImportRequest;
use PHPeste\Ticketing\Http\Resources\TicketImportResource;
use PHPeste\Ticketing\Imports\ImportPreview;
use PHPeste\Ticketing\Models\TicketImport;
use PHPeste\Ticketing\Models\TicketImportRejection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importação de compradores de outra plataforma: enviar o CSV, mapear as
 * colunas com prévia, importar em fila e, só com confirmação, mandar os
 * ingressos por e-mail.
 */
class TicketImportController
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return TicketImportResource::collection(TicketImport::query()->latest()->orderByDesc('id')->paginate(25));
    }

    public function store(UploadTicketImportRequest $request, UploadTicketImport $upload, CurrentEdition $edition, ImportPreview $preview): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $import = $upload->handle((string) $edition->id(), $request->upload(), $request->platform(), $this->actor($request));

        return $this->withPreview($import, $preview)->response()->setStatusCode(201);
    }

    public function show(Request $request, ImportPreview $preview): TicketImportResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return $this->withPreview($this->import($request), $preview);
    }

    public function map(MapTicketImportRequest $request, MapTicketImport $map, ImportPreview $preview): TicketImportResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $import = $map->handle($this->import($request), $request->mapping(), $request->ticketTypeMap(), $request->defaultTicketTypeId());

        return $this->withPreview($import, $preview);
    }

    public function start(Request $request, StartTicketImport $start): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return new TicketImportResource($start->handle($this->import($request), $this->actor($request)))->response()->setStatusCode(202);
    }

    public function resume(Request $request, ResumeTicketImport $resume): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return new TicketImportResource($resume->handle($this->import($request)))->response()->setStatusCode(202);
    }

    public function destroy(Request $request, DiscardTicketImport $discard): Response
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $discard->handle($this->import($request));

        return response()->noContent();
    }

    public function rejections(Request $request): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $import = $this->import($request);
        $page = TicketImportRejection::query()->where('ticket_import_id', $import->id)->orderBy('line')->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (TicketImportRejection $rejection): array => [
                'line' => $rejection->line,
                'kind' => $rejection->kind->value,
                'reason' => $rejection->reason,
                'values' => $rejection->data,
            ])->all(),
            'headers' => $import->headers,
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    /**
     * Relatório das linhas que não entraram, em CSV com o mesmo cabeçalho
     * do arquivo original mais a linha e o motivo, para corrigir e reenviar.
     */
    public function rejectionsCsv(Request $request): StreamedResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $import = $this->import($request);

        // O corpo é gerado depois que a requisição já saiu do contexto da
        // edição, por isso a consulta abaixo diz a edição explicitamente.
        return response()->streamDownload(function () use ($import): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Linha', 'Situação', 'Motivo', ...$import->headers], ';', '"', '');

            TicketImportRejection::query()->forEdition($import->edition_id)->where('ticket_import_id', $import->id)
                ->lazyById(500)
                ->each(function (TicketImportRejection $rejection) use ($out): void {
                    fputcsv($out, [(string) $rejection->line, $rejection->kind->value === 'duplicate' ? 'Repetida' : 'Rejeitada', $rejection->reason, ...$rejection->data], ';', '"', '');
                });

            fclose($out);
        }, sprintf('importacao-%s-linhas-de-fora.csv', mb_substr($import->id, 0, 8)), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function email(EmailImportedTicketsRequest $request, EmailImportedTickets $email): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $count = $email->handle($this->import($request), $request->boolean('confirm'), $request->integer('recipients'), $this->actor($request));

        return response()->json(['data' => ['queued' => $count]], 202);
    }

    private function withPreview(TicketImport $import, ImportPreview $preview): TicketImportResource
    {
        $resource = new TicketImportResource($import);
        $resource->preview = $import->status->editable() ? $preview->build($import) : null;

        return $resource;
    }

    private function import(Request $request): TicketImport
    {
        $id = $request->route('import');
        abort_unless(is_string($id), 404);

        return TicketImport::query()->whereKey($id)->firstOrFail();
    }

    private function actor(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_string($id) ? $id : null;
    }
}
