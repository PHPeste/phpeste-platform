<?php

declare(strict_types=1);

use App\Support\Api\ApiError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

dataset('api errors', [
    'validação' => fn (): array => [
        new ValidationException(Validator::make([], ['name' => ['required']])),
        422,
        'validation_error',
    ],
    'não autenticado' => fn (): array => [new AuthenticationException, 401, 'unauthenticated'],
    'sem permissão' => fn (): array => [new AccessDeniedHttpException, 403, 'forbidden'],
    'não encontrado' => fn (): array => [new NotFoundHttpException, 404, 'not_found'],
    'método não permitido' => fn (): array => [new MethodNotAllowedHttpException([]), 405, 'method_not_allowed'],
    'muitas tentativas' => fn (): array => [new TooManyRequestsHttpException, 429, 'too_many_requests'],
    'erro genérico' => fn (): array => [new RuntimeException('boom'), 500, 'server_error'],
]);

it('monta o envelope certo pra cada tipo de erro', function (Throwable $exception, int $status, string $code): void {
    $request = Request::create('/api/v1/ping');
    $request->attributes->set('request_id', 'test-request-id');

    $response = ApiError::render($exception, $request);

    expect($response->getStatusCode())->toBe($status)
        ->and($response->getData(true))
        ->toMatchArray(['code' => $code, 'request_id' => 'test-request-id']);
})->with('api errors');

it('só preenche errors em erro de validação', function (): void {
    $request = Request::create('/api/v1/ping');

    $validationResponse = ApiError::render(
        new ValidationException(Validator::make([], ['name' => ['required']])),
        $request,
    );
    $notFoundResponse = ApiError::render(new NotFoundHttpException, $request);

    expect($validationResponse->getData(true)['errors'])->not->toBeNull()
        ->and($notFoundResponse->getData(true)['errors'])->toBeNull();
});
