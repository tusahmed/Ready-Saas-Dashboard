<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SafeLogoUpload
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    private const MAX_PIXELS = 4 * 1024 * 1024;

    public function store(UploadedFile $file, string $scope): string
    {
        // Never let a caller turn a workspace identifier into a filesystem path.
        if (preg_match('/\A(?:platform|tenant-[1-9][0-9]*)\z/', $scope) !== 1) {
            $this->invalid();
        }

        // A single, explicit raster extension also rejects .php.png, .phtml.jpg,
        // hidden files, encoded nulls, control characters, and filename delimiters.
        if (! $file->isValid()
            || preg_match('/\A[\pL\pN][\pL\pN _-]{0,119}\.(png|jpe?g|webp)\z/ui', $file->getClientOriginalName(), $matches) !== 1
            || $file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) {
            $this->invalid();
        }

        $bytes = @file_get_contents($file->getRealPath());
        if (! is_string($bytes)) {
            $this->invalid();
        }
        $extension = strtolower($matches[1]);
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $sanitized = $this->sanitize($bytes, $extension);
        $path = 'workspace-logos/'.$scope.'/sanitized/'.Str::random(40).'.'.$extension;
        if (! Storage::disk('local')->put($path, $sanitized, ['visibility' => 'private'])) {
            Storage::disk('local')->delete($path);
            $this->failed();
        }

        return $path;
    }

    public function sanitize(string $bytes, string $extension): string
    {
        if (strlen($bytes) < 1 || strlen($bytes) > self::MAX_BYTES) {
            $this->invalid();
        }
        // Fail closed when the hosting account does not have the image decoder.
        if (! extension_loaded('gd') || ! extension_loaded('fileinfo')) {
            $this->failed();
        }

        $info = @getimagesizefromstring($bytes);
        $type = $info[2] ?? null;
        $detectedExtension = match ($type) {
            IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', default => null,
        };
        if (! $detectedExtension || $detectedExtension !== $extension
            || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== image_type_to_mime_type($type)
            || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096
            || $info[0] * $info[1] > self::MAX_PIXELS) {
            $this->invalid();
        }

        $this->requireMemory($info[0] * $info[1]);
        if ($type === IMAGETYPE_WEBP && ! function_exists('imagewebp')) {
            $this->failed();
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            $this->invalid();
        }

        $clean = null;
        $bufferLevel = ob_get_level();
        try {
            // Copy decoded pixels to a fresh canvas. Original bytes, EXIF,
            // comments, animation frames and appended PHP/polyglot data are never stored.
            $clean = imagecreatetruecolor($info[0], $info[1]);
            if (! $clean instanceof GdImage) {
                $this->failed();
            }
            imagealphablending($clean, false);
            imagesavealpha($clean, true);
            if (! imagecopy($clean, $source, 0, 0, 0, 0, $info[0], $info[1])) {
                $this->failed();
            }
            ob_start();
            $encoded = match ($type) {
                IMAGETYPE_PNG => imagepng($clean, null, 6),
                IMAGETYPE_JPEG => imagejpeg($clean, null, 90),
                IMAGETYPE_WEBP => imagewebp($clean, null, 85),
            };
            $sanitized = ob_get_clean();
            if (! $encoded || ! is_string($sanitized) || $sanitized === '') {
                $this->failed();
            }
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            imagedestroy($source);
            if ($clean instanceof GdImage) {
                imagedestroy($clean);
            }
        }

        return $sanitized;
    }

    private function requireMemory(int $pixels): void
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '-1') {
            return;
        }
        $multiplier = match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        };
        $available = (int) $limit * $multiplier - memory_get_usage(true);
        // Budget for both GD canvases, decoder/encoder workspace and request overhead.
        if ($available < $pixels * 16 + 16 * 1024 * 1024) {
            $this->invalid();
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['logo' => __('validation.image', ['attribute' => __('app.logo')])]);
    }

    private function failed(): never
    {
        throw ValidationException::withMessages(['logo' => __('app.logo_upload_failed')]);
    }
}
