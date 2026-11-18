<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use PHPeste\Identity\Actions\ChangePassword;
use PHPeste\Identity\Actions\ResetPassword;
use PHPeste\Identity\Actions\SendPasswordResetLink;
use PHPeste\Identity\Http\Requests\ChangePasswordRequest;
use PHPeste\Identity\Http\Requests\ForgotPasswordRequest;
use PHPeste\Identity\Http\Requests\ResetPasswordRequest;
use PHPeste\Identity\Models\User;

class PasswordController
{
    public function forgot(ForgotPasswordRequest $request, SendPasswordResetLink $sendLink): JsonResponse
    {
        $sendLink->handle($request->string('email')->toString());

        return response()->json([
            'sent' => true,
            'message' => 'Se existir uma conta com este e-mail, enviamos um link para redefinir a senha.',
        ]);
    }

    public function reset(ResetPasswordRequest $request, ResetPassword $resetPassword): JsonResponse
    {
        $resetPassword->handle(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json(['reset' => true]);
    }

    public function update(ChangePasswordRequest $request, ChangePassword $changePassword): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $changePassword->handle(
            $user,
            $request->filled('current_password') ? $request->string('current_password')->toString() : null,
            $request->string('password')->toString(),
            $this->currentTokenId($user),
        );

        return response()->json(['updated' => true]);
    }

    private function currentTokenId(User $user): string|int|null
    {
        $id = $user->currentAccessToken()->getKey();

        return is_string($id) || is_int($id) ? $id : null;
    }
}
