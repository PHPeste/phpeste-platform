<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Actions\RequestEditionClone;
use PHPeste\Community\Cloning\EditionCloners;
use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Http\Requests\CloneEditionRequest;
use PHPeste\Community\Http\Resources\EditionCloneResource;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionClone;
use PHPeste\Community\Tenancy\CurrentEdition;

class EditionCloneController
{
    public function options(EditionCloners $cloners): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return response()->json([
            'data' => [
                'parts' => array_values(array_map(fn (EditionCloner $cloner): array => ['id' => $cloner->key(), 'label' => $cloner->label()], $cloners->all())),
                'never_copied' => ['Propostas', 'Pedidos e ingressos vendidos', 'Participantes', 'Grade', 'Dinheiro e chaves do gateway', 'Domínios', 'Datas'],
            ],
        ]);
    }

    public function store(CloneEditionRequest $request, CurrentEdition $currentEdition, RequestEditionClone $requestClone): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $clone = $requestClone->handle(
            Edition::query()->findOrFail($currentEdition->id()),
            $request->string('name')->toString(),
            $request->string('slug')->toString(),
            $request->parts(),
            (string) $request->user()?->getAuthIdentifier(),
        );

        return new EditionCloneResource($clone)->response()->setStatusCode(202);
    }

    public function show(Request $request): EditionCloneResource
    {
        $id = $request->route('clone');

        abort_unless(is_string($id), 404);

        $clone = EditionClone::query()
            ->whereKey($id)
            ->where('requested_by', (string) $request->user()?->getAuthIdentifier())
            ->firstOrFail();

        return new EditionCloneResource($clone);
    }
}
