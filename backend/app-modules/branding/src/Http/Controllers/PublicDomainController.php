<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPeste\Branding\Domains\DomainResolver;
use PHPeste\Branding\Domains\Hostname;

class PublicDomainController
{
    public function resolve(Request $request, DomainResolver $resolver): JsonResponse
    {
        $match = $this->match($request->query('host'), $resolver);

        abort_if($match === null, 404, 'Nenhuma edição publicada neste domínio.');

        return response()->json(['data' => $match])
            ->setPublic()
            ->setMaxAge(DomainResolver::CACHE_SECONDS);
    }

    public function tlsCheck(Request $request, DomainResolver $resolver): Response
    {
        abort_if($this->match($request->query('domain'), $resolver) === null, 404);

        return response()->noContent(200);
    }

    /**
     * @return array{edition_id: string, edition_name: string, host: string, primary_host: string}|null
     */
    private function match(mixed $host, DomainResolver $resolver): ?array
    {
        $normalized = is_string($host) ? Hostname::normalize($host) : null;

        return $normalized === null ? null : $resolver->resolve($normalized);
    }
}
