<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotencyKey
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (!is_string($key) || $key === '') {
            return response()->json([
                'code' => 'idempotency_key_required',
                'message' => 'O header Idempotency-Key é obrigatório para esta operação.',
                'errors' => null,
                'request_id' => $request->attributes->get('request_id'),
            ], 400);
        }

        $cacheKey = "idempotency:{$key}";

        /** @var array{status: int, body: array<string, mixed>}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return response()->json($cached['body'], $cached['status']);
        }

        $response = $next($request);

        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getData(true),
            ], now()->addDay());
        }

        return $response;
    }
}
