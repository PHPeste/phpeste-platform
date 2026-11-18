<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPeste\Notifications\Actions\Unsubscribe;

class UnsubscribeController
{
    public function __invoke(Request $request, string $edition, Unsubscribe $unsubscribe): Response|JsonResponse
    {
        $email = $request->query('email');

        abort_unless(is_string($email) && $email !== '', 404);

        $unsubscribe->handle($edition, $email);

        if ($request->isMethod('post') || $request->expectsJson()) {
            return response()->json(['unsubscribed' => true]);
        }

        return response()->view('notifications::unsubscribed');
    }
}
