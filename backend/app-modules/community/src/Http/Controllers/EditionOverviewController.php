<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Overview\EditionOverview;
use PHPeste\Community\Tenancy\CurrentEdition;

class EditionOverviewController
{
    public function __invoke(Request $request, CurrentEdition $currentEdition, EditionOverview $overview): JsonResponse
    {
        /** @var Authenticatable $user */
        $user = $request->user();

        return response()->json(['data' => $overview->build(Edition::query()->findOrFail($currentEdition->id()), $user)]);
    }
}
