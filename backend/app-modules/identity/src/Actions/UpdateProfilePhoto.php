<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Identity\Models\User;

class UpdateProfilePhoto
{
    public const int MIN_SIZE = 600;

    public const int OUTPUT_SIZE = 800;

    public function handle(User $user, UploadedFile $file): User
    {
        $image = $this->decode($file);
        $square = $this->cropAndResize($image);

        ob_start();
        imagejpeg($square, null, 85);
        $jpeg = (string) ob_get_clean();

        $disk = Storage::disk((string) config('filesystems.media'));
        $path = "users/{$user->id}/photo-".Str::uuid().'.jpg';
        $previous = $user->photo_path;

        $disk->put($path, $jpeg);
        $user->update(['photo_path' => $path]);

        if ($previous !== null) {
            $disk->delete($previous);
        }

        return $user;
    }

    private function decode(UploadedFile $file): GdImage
    {
        $contents = $file->getContent();
        $info = @getimagesizefromstring($contents);
        $image = $info === false ? false : @imagecreatefromstring($contents);

        if ($info === false || !$image instanceof GdImage || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['photo' => 'O arquivo enviado não é uma imagem JPG, PNG ou WebP válida.']);
        }

        if (min(imagesx($image), imagesy($image)) < self::MIN_SIZE) {
            throw ValidationException::withMessages(['photo' => sprintf('A foto precisa ter pelo menos %d × %d px.', self::MIN_SIZE, self::MIN_SIZE)]);
        }

        return $image;
    }

    private function cropAndResize(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);

        $square = imagecreatetruecolor(self::OUTPUT_SIZE, self::OUTPUT_SIZE);
        imagefill($square, 0, 0, (int) imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled(
            $square,
            $image,
            0,
            0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            self::OUTPUT_SIZE,
            self::OUTPUT_SIZE,
            $side,
            $side,
        );

        return $square;
    }
}
