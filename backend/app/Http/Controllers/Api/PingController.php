<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Confirma que a API está no ar, sem tocar banco ou fila.
 */
class PingController extends Controller
{
    /**
     * @response array{status: string, request_id: string|null}
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'request_id' => request()->attributes->get('request_id'),
        ]);
    }
}
