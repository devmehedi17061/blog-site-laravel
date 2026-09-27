<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Smaller WebP copies of an uploaded image, for srcset.
 *
 * Widths are chosen for the layouts on this site: a phone (480), a post
 * column on a laptop (960), and a full-width band (1600) — plus one at the
 * original width, which as WebP is usually a fraction of a JPEG or PNG.
 * Nothing is made larger than the original, and the original is kept.
 *
 * Uses GD. Without it, or for anything GD cannot read, the upload is left
 * exactly as it was: optimisation is a bonus, never a way to lose a file.
 */
class ImageVariants
{
    public const WIDTHS = [480, 960, 1600];

    private const QUALITY = 80;

    /** Raster types GD can read. SVG and GIF (which may be animated) are left alone. */
    private const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function supported(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    /**
     * Makes (or remakes) the copies for one file and saves its dimensions.
     *
     * @return bool whether any copies were made
     */
    public static function generate(Media $media): bool
    {
        if (! self::supported() || ! in_array($media->mime_type, self::TYPES, true)) {
            return false;
        }

        $disk = Storage::disk($media->disk);

        try {
            $bytes = $disk->get($media->path);
            $size = $bytes ? @getimagesizefromstring($bytes) : false;

            if (! $size) {
                return false;
            }

            [$width, $height] = $size;

            // A photo from a phone is 12+ megapixels; GD needs about 5 bytes
            // a pixel. Past this, leave it alone rather than exhaust memory.
            if ($width * $height > 40_000_000) {
                $media->forceFill(['width' => $width, 'height' => $height])->saveQuietly();

                return false;
            }

            @ini_set('memory_limit', '512M');
            $source = @imagecreatefromstring($bytes);

            if (! $source) {
                return false;
            }

            self::removeFiles($media);

            $variants = [];
            $base = 'media/variants/'.pathinfo($media->path, PATHINFO_FILENAME);

            // A full-width WebP is only worth making from a JPEG or PNG; an
            // original that is already WebP serves as its own largest size.
            $targets = array_filter(self::WIDTHS, fn ($w) => $w < $width);
            if ($media->mime_type !== 'image/webp') {
                $targets[] = $width;
            }

            foreach ($targets as $target) {
                $copy = $target === $width ? $source : self::resized($source, $width, $height, $target);
                $path = "{$base}-{$target}.webp";

                ob_start();
                imagewebp($copy, null, self::QUALITY);
                $disk->put($path, (string) ob_get_clean());

                if ($copy !== $source) {
                    imagedestroy($copy);
                }

                $variants[(string) $target] = $path;
            }

            imagedestroy($source);

            $media->forceFill(['width' => $width, 'height' => $height, 'variants' => $variants])->saveQuietly();

            return true;
        } catch (Throwable $e) {
            Log::warning('Could not make image copies', ['media' => $media->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Deletes the copies (the original is the caller's business). */
    public static function removeFiles(Media $media): void
    {
        $paths = array_values((array) ($media->variants ?? []));

        if ($paths) {
            Storage::disk($media->disk)->delete($paths);
        }
    }

    private static function resized(\GdImage $source, int $width, int $height, int $target): \GdImage
    {
        $newHeight = max(1, (int) round($height * $target / $width));
        $copy = imagecreatetruecolor($target, $newHeight);

        // Keep transparency in PNGs and WebPs.
        imagealphablending($copy, false);
        imagesavealpha($copy, true);
        imagefill($copy, 0, 0, imagecolorallocatealpha($copy, 0, 0, 0, 127));

        imagecopyresampled($copy, $source, 0, 0, 0, 0, $target, $newHeight, $width, $height);

        return $copy;
    }
}
