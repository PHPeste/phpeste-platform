<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Http\Resources\ReconciliationIssueResource;
use PHPeste\Ticketing\Models\ReconciliationIssue;
use PHPeste\Ticketing\Reconciliation\ReconcileOrders;

class ReconciliationController
{
    public function __construct(private readonly CurrentEdition $currentEdition) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(EditionPermission::ViewOrders->value);

        $resolved = $request->query('status') === 'resolved';

        $issues = ReconciliationIssue::query()
            ->when($resolved, fn ($query) => $query->whereNotNull('resolved_at'), fn ($query) => $query->whereNull('resolved_at'))
            ->latest('last_seen_at')
            ->orderByDesc('id')
            ->paginate(25);

        return ReconciliationIssueResource::collection($issues);
    }

    public function run(ReconcileOrders $reconcile): JsonResponse
    {
        Gate::authorize(EditionPermission::RefundOrders->value);

        return response()->json(['data' => $reconcile->handle((string) $this->currentEdition->id())]);
    }

    public function resolve(Request $request): ReconciliationIssueResource
    {
        Gate::authorize(EditionPermission::RefundOrders->value);

        $request->validate(['resolution' => ['required', 'string', 'max:500']], ['resolution.required' => 'Conte o que foi feito para resolver a divergência.']);
        $id = $request->route('issue');
        abort_unless(is_string($id), 404);

        $issue = ReconciliationIssue::query()->whereKey($id)->whereNull('resolved_at')->firstOrFail();
        $actor = $request->user()?->getAuthIdentifier();
        $issue->update(['resolved_at' => now(), 'resolved_by' => is_string($actor) ? $actor : null, 'resolution' => (string) $request->string('resolution')]);

        return new ReconciliationIssueResource($issue);
    }
}
