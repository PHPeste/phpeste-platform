<?php

declare(strict_types=1);

namespace App\Support\Api;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiError
{
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return self::json(422, 'validation_error', 'Os dados enviados são inválidos.', $e->errors(), $request);
        }

        [$status, $code, $message] = self::resolve($e);

        return self::json($status, $code, $message, null, $request);
    }

    /**
     * @param  array<array-key, mixed>|null  $errors
     */
    private static function json(int $status, string $code, string $message, ?array $errors, Request $request): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'errors' => $errors,
            'request_id' => $request->attributes->get('request_id'),
        ], $status);
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private static function resolve(Throwable $e): array
    {
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : null;

        return match (true) {
            $e instanceof AuthenticationException => [
                401, 'unauthenticated', 'É preciso autenticar para acessar este recurso.',
            ],
            $status === 403 => [
                403, 'forbidden', 'Você não tem permissão para esta ação.',
            ],
            $status === 404 => [
                404, 'not_found', 'Recurso não encontrado.',
            ],
            $status === 410 => [
                410, 'gone', $e->getMessage() ?: 'Este recurso não está mais disponível.',
            ],
            $status === 405 => [
                405, 'method_not_allowed', 'Método HTTP não permitido para esta rota.',
            ],
            $status === 429 => [
                429, 'too_many_requests', 'Muitas tentativas. Tente novamente mais tarde.',
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(), 'http_error', $e->getMessage() ?: 'Erro ao processar a requisição.',
            ],
            default => [
                500, 'server_error', config('app.debug') ? $e->getMessage() : 'Erro interno. Tente novamente mais tarde.',
            ],
        };
    }
}
