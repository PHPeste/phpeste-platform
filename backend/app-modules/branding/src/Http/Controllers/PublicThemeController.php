<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Branding\Theme\PublicTheme;
use PHPeste\Community\Contracts\EditionDirectory;

class PublicThemeController
{
    public function __invoke(Request $request, string $edition, EditionDirectory $editions): JsonResponse
    {
        abort_unless($editions->isPublished($edition), 404);

        $payload = ['data' => PublicTheme::for($edition)];

        $response = response()->json($payload)
            ->setPublic()
            ->setMaxAge(300)
            ->setEtag(sha1((string) json_encode($payload)));

        $response->isNotModified($request);

        return $response;
    }
}
