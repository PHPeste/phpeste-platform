<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPeste\Notifications\Events\AddressSuppressionChanged;
use PHPeste\Notifications\Models\EmailSuppression;

/**
 * Endereços bloqueados da edição. Liberar volta a permitir envio; se o
 * endereço continuar inválido, o próximo bounce bloqueia de novo.
 */
class EmailSuppressionController
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage-edition-emails');

        $search = mb_strtolower(mb_trim((string) $request->query('q')));
        $page = EmailSuppression::query()
            ->when($search !== '', fn ($query) => $query->where('email', 'like', "%{$search}%"))
            ->latest()->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (EmailSuppression $suppression): array => [
                'id' => $suppression->id,
                'email' => $suppression->email,
                'reason' => $suppression->reason->value,
                'reason_label' => $suppression->reason->label(),
                'detail' => $suppression->detail,
                'delivery_id' => $suppression->email_delivery_id,
                'created_at' => $suppression->created_at->clone()->utc()->toIso8601ZuluString(),
            ])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function destroy(Request $request): Response
    {
        Gate::authorize('manage-edition-emails');

        $id = $request->route('suppression');
        abort_unless(is_string($id), 404);
        $suppression = EmailSuppression::query()->whereKey($id)->firstOrFail();
        $actor = $request->user()?->getAuthIdentifier();

        DB::transaction(function () use ($suppression, $actor): void {
            $suppression->delete();
            event(new AddressSuppressionChanged($suppression->edition_id, $suppression->id, $suppression->email, $suppression->reason->value, true, is_string($actor) ? $actor : null));
        });

        return response()->noContent();
    }
}
