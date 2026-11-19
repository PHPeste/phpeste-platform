<?php

declare(strict_types=1);

namespace PHPeste\Payments\Drivers\MercadoPago;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPeste\Payments\Data\GatewayCredentials;
use PHPeste\Payments\Gateway\GatewayRequestFailed;
use Throwable;

class MercadoPagoClient
{
    public const string BASE_URL = 'https://api.mercadopago.com';

    public const string USER_AGENT = 'PHPeste-Platform';

    /**
     * @return array<string, mixed>
     */
    public function get(GatewayCredentials $credentials, string $path): array
    {
        return $this->send($credentials, 'get', $path, []);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(GatewayCredentials $credentials, string $path, array $body, ?string $idempotencyKey = null): array
    {
        return $this->send($credentials, 'post', $path, $body, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function put(GatewayCredentials $credentials, string $path, array $body): array
    {
        return $this->send($credentials, 'put', $path, $body);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(GatewayCredentials $credentials, string $method, string $path, array $payload, ?string $idempotencyKey = null): array
    {
        $request = $this->request($credentials, $idempotencyKey);

        try {
            $response = match ($method) {
                'get' => $request->get($path),
                'put' => $request->put($path, $payload),
                default => $request->post($path, $payload),
            };
        } catch (ConnectionException $exception) {
            throw new GatewayRequestFailed("Mercado Pago inacessível em {$method} {$path}.", GatewayRequestFailed::UNAVAILABLE, null, $exception);
        } catch (RequestException $exception) {
            $response = $exception->response;
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw $this->failure($response, $method, $path);
    }

    private function request(GatewayCredentials $credentials, ?string $idempotencyKey): PendingRequest
    {
        $headers = ['User-Agent' => self::USER_AGENT];

        if ($idempotencyKey !== null) {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }

        return Http::baseUrl(self::BASE_URL)
            ->withToken($credentials->apiKey)
            ->withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(3, fn (int $attempt): int => 200 * $attempt, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)), throw: false);
    }

    private function failure(Response $response, string $method, string $path): GatewayRequestFailed
    {
        $causes = [];

        foreach ((array) $response->json('cause', []) as $cause) {
            if (is_array($cause)) {
                $causes[] = sprintf('%s %s', (string) ($cause['code'] ?? ''), (string) ($cause['description'] ?? ''));
            }
        }

        $message = (string) $response->json('message', '');

        $reason = match (true) {
            $response->status() === 401 => GatewayRequestFailed::INVALID_CREDENTIALS,
            $response->status() === 404 => GatewayRequestFailed::NOT_FOUND,
            $response->serverError(), $response->status() === 429 => GatewayRequestFailed::UNAVAILABLE,
            $this->payerProblem($message.' '.implode(' ', $causes)) => GatewayRequestFailed::INVALID_PAYER,
            default => GatewayRequestFailed::REJECTED,
        };

        Log::warning('payments.mercadopago_error', ['method' => $method, 'path' => $path, 'status' => $response->status(), 'message' => $message, 'causes' => $causes]);

        return new GatewayRequestFailed(
            sprintf('Mercado Pago respondeu %d em %s %s: %s', $response->status(), $method, $path, $message !== '' ? $message : 'sem descrição'),
            $reason,
            $response->status(),
        );
    }

    private function payerProblem(string $text): bool
    {
        return array_any(['payer', 'identification', 'email'], fn (string $field): bool => str_contains(mb_strtolower($text), $field));
    }
}
