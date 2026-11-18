<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PHPeste\Identity\Actions\RemoveProfilePhoto;
use PHPeste\Identity\Actions\UpdateAccount;
use PHPeste\Identity\Actions\UpdateProfilePhoto;
use PHPeste\Identity\Http\Requests\UpdateAccountRequest;
use PHPeste\Identity\Http\Requests\UploadPhotoRequest;
use PHPeste\Identity\Http\Resources\UserResource;
use PHPeste\Identity\Models\User;

class ProfileController
{
    public function update(UpdateAccountRequest $request, UpdateAccount $updateAccount): UserResource
    {
        /** @var array{name?: string, locale?: string} $attributes */
        $attributes = $request->validated();

        return new UserResource($updateAccount->handle($this->user($request), $attributes));
    }

    public function uploadPhoto(UploadPhotoRequest $request, UpdateProfilePhoto $updatePhoto): UserResource
    {
        /** @var UploadedFile $photo */
        $photo = $request->file('photo');

        return new UserResource($updatePhoto->handle($this->user($request), $photo));
    }

    public function deletePhoto(Request $request, RemoveProfilePhoto $removePhoto): UserResource
    {
        return new UserResource($removePhoto->handle($this->user($request)));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
