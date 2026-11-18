<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Support\Facades\Storage;
use PHPeste\Identity\Models\User;

class RemoveProfilePhoto
{
    public function handle(User $user): User
    {
        if ($user->photo_path !== null) {
            Storage::disk((string) config('filesystems.media'))->delete($user->photo_path);
            $user->update(['photo_path' => null]);
        }

        return $user;
    }
}
