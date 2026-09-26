<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Private file storage for delivery evidence. Files live on the private "local"
 * disk (storage/app/private), outside the web root, under random names, and are
 * streamed only by authorised controllers.
 */
class FileStorage
{
    private const DISK = 'local';

    public function storeImage(UploadedFile $file, string $folder): string
    {
        $mime = $file->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['photo' => 'Only JPEG, PNG or WebP images are accepted.']);
        }
        if ($file->getSize() > config('courier.uploads.max_kb') * 1024) {
            throw ValidationException::withMessages(['photo' => 'The image is too large.']);
        }
        if (@getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages(['photo' => 'The file is not a valid image.']);
        }
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];

        return $file->storeAs($folder.'/'.now()->format('Y/m'), Str::random(40).'.'.$ext, self::DISK);
    }

    /** Store a signature captured from a canvas as a PNG data URL. */
    public function storeSignature(string $dataUrl, string $folder): string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw ValidationException::withMessages(['signature' => 'The signature could not be read. Please sign again.']);
        }
        $bin = base64_decode($m[1], true);
        if ($bin === false || strlen($bin) > 512 * 1024 || ! str_starts_with($bin, "\x89PNG\r\n\x1a\n") || @getimagesizefromstring($bin) === false) {
            throw ValidationException::withMessages(['signature' => 'The signature image is invalid.']);
        }
        $path = $folder.'/'.now()->format('Y/m').'/'.Str::random(40).'.png';
        Storage::disk(self::DISK)->put($path, $bin);

        return $path;
    }

    public function exists(?string $path): bool
    {
        return $path && Storage::disk(self::DISK)->exists($path);
    }

    public function response(string $path)
    {
        return Storage::disk(self::DISK)->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
