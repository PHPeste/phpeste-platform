<?php

declare(strict_types=1);

namespace PHPeste\Payments\Drivers\Asaas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPeste\Payments\Data\GatewayCredentials;
use PHPeste\Payments\Enums\GatewayEnvironment;
use PHPeste\Payments\Gateway\GatewayRequestFailed;
use Throwable;

class AsaasClient
{
    public const string SANDBOX_URL = 'https://api-sandbox.asaas.com/v3';

    public const string PRODUCTION_URL = 'https://api.asaas.com/v3';

    public const string USER_AGENT = 'PHPeste-Platform';

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(GatewayCredentials $credentials, string $path, array $query = []): array
    {
        return $this->send($credentials, 'get', $path, $query, retry: true);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(GatewayCredentials $credentials, string $path, array $body): array
    {
        return $this->send($credentials, 'post', $path, $body, retry: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(GatewayCredentials $credentials, string $path): array
    {
        return $this->send($credentials, 'delete', $path, [], retry: true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(GatewayCredentials $credentials, string $method, string $path, array $payload, bool $retry): array
    {
        $request = $this->request($credentials, $retry);

        try {
            $response = match ($method) {
                'get' => $request->get($path, $payload),
                'delete' => $request->delete($path),
                default => $request->post($path, $payload),
            };
        } catch (ConnectionException $exception) {
            throw new GatewayRequestFailed("Asaas inacessível em {$method} {$path}.", GatewayRequestFailed::UNAVAILABLE, null, $exception);
        } catch (RequestException $exception) {
            $response = $exception->response;
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw $this->failure($response, $method, $path);
    }

    private function request(GatewayCredentials $credentials, bool $retry): PendingRequest
    {
        $request = Http::baseUrl($credentials->environment === GatewayEnvironment::Production ? self::PRODUCTION_URL : self::SANDBOX_URL)
            ->withHeaders(['access_token' => $credentials->apiKey, 'User-Agent' => self::USER_AGENT])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);

        if ($retry) {
            $request->retry(3, fn (int $attempt): int => 200 * $attempt, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)), throw: false);
        }

        return $request;
    }

    private function failure(Response $response, string $method, string $path): GatewayRequestFailed
    {
        $codes = [];
        $descriptions = [];

        foreach ((array) $response->json('errors', []) as $error) {
            if (is_array($error)) {
                $codes[] = (string) ($error['code'] ?? '');
                $descriptions[] = (string) ($error['description'] ?? '');
            }
        }

        $reason = match (true) {
            $response->status() === 401 => GatewayRequestFailed::INVALID_CREDENTIALS,
            $response->status() === 404 => GatewayRequestFailed::NOT_FOUND,
            $response->serverError(), $response->status() === 429 => GatewayRequestFailed::UNAVAILABLE,
            $this->payerProblem($codes) => GatewayRequestFailed::INVALID_PAYER,
            default => GatewayRequestFailed::REJECTED,
        };

        Log::warning('payments.asaas_error', ['method' => $method, 'path' => $path, 'status' => $response->status(), 'codes' => $codes, 'descriptions' => $descriptions]);

        return new GatewayRequestFailed(
            sprintf('Asaas respondeu %d em %s %s: %s', $response->status(), $method, $path, implode('; ', array_filter($descriptions)) ?: 'sem descrição'),
            $reason,
            $response->status(),
        );
    }

    /**
     * @param  list<string>  $codes
     */
    private function payerProblem(array $codes): bool
    {
        return array_any($codes, fn (string $code): bool => str_contains($code, 'cpfCnpj') || str_contains($code, 'customer') || str_contains($code, 'email') || str_contains($code, 'name'));
    }
}
