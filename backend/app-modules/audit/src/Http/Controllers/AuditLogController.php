<?php

declare(strict_types=1);

namespace PHPeste\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use PHPeste\Audit\Http\Requests\ListAuditLogsRequest;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Identity\Contracts\UserAccounts;

class AuditLogController
{
    public function __invoke(ListAuditLogsRequest $request, CurrentEdition $currentEdition, UserAccounts $accounts): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $logs = AuditLog::query()
            ->where('edition_id', $currentEdition->id())
            ->when($request->filled('actor_id'), fn ($query) => $query->where('actor_id', $request->string('actor_id')->toString()))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', Date::parse($request->string('from')->toString())->utc()))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', Date::parse($request->string('to')->toString())->utc()))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50);

        $actorIds = array_values(array_unique(array_filter($logs->getCollection()->map(fn (AuditLog $log): ?string => $log->actor_id)->all())));
        $actors = $accounts->profiles($actorIds);

        return response()->json([
            'data' => array_values($logs->getCollection()->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'actor' => $log->actor_id === null ? null : ['id' => $log->actor_id, 'name' => $actors[$log->actor_id]['name'] ?? null],
                'before' => $log->before,
                'after' => $log->after,
                'ip' => $log->ip,
                'request_id' => $log->request_id,
                'created_at' => $log->created_at->clone()->utc()->toIso8601ZuluString(),
            ])->all()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
