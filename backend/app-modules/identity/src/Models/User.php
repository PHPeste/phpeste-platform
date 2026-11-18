<?php

declare(strict_types=1);

namespace PHPeste\Identity\Models;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use PHPeste\Identity\Database\Factories\UserFactory;
use PHPeste\Identity\Notifications\ResetPasswordNotification;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string $locale
 * @property string|null $photo_path
 * @property string|null $remember_token
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, SocialAccount> $socialAccounts
 */
#[Fillable(['name', 'email', 'password', 'locale', 'photo_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use MustVerifyEmail;
    use Notifiable;

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return HasOne<SpeakerProfile, $this>
     */
    public function speakerProfile(): HasOne
    {
        return $this->hasOne(SpeakerProfile::class);
    }

    /**
     * @return HasOne<SpeakerPrivateDetails, $this>
     */
    public function speakerPrivateDetails(): HasOne
    {
        return $this->hasOne(SpeakerPrivateDetails::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path === null ? null : Storage::disk((string) config('filesystems.media'))->url($this->photo_path);
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
