<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Tenancy\CurrentEdition;
use Symfony\Component\HttpFoundation\Response;

class ResolveEdition
{
    public function __construct(private readonly CurrentEdition $currentEdition) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $parameter = $route instanceof Route ? $route->parameter('edition') : null;

        $edition = $parameter instanceof Edition
            ? $parameter
            : Edition::query()->find(is_string($parameter) ? $parameter : null);

        abort_if($edition === null, 404);

        $userId = $request->user()?->getAuthIdentifier();

        abort_if(!is_string($userId) || $edition->roleOf($userId) === null, 403);

        $this->currentEdition->set($edition->id);

        try {
            return $next($request);
        } finally {
            $this->currentEdition->clear();
        }
    }
}
