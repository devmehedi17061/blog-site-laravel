<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Uploaded lesson videos, on the private "lessons" disk.
 *
 * A video arrives in chunks (a few MB each), so a 1 GB file gets past PHP's
 * upload_max_filesize and post_max_size on any host. The chunks wait in
 * chunks/{course}/{upload id}/ until the last one lands, then become one
 * file at {course}/{40 random characters}.{ext}. That path is what a lesson
 * stores; nothing on the public site points at it — LearnController::video()
 * streams it to people allowed to watch.
 */
class LessonVideos
{
    /** What the browser sends per request. The server allows a little over. */
    public const CHUNK_BYTES = 5 * 1024 * 1024;

    public const EXTENSIONS = ['mp4', 'm4v', 'webm', 'ogv', 'mov'];

    /** Unfinished uploads older than this are thrown away. */
    private const STALE_HOURS = 24;

    public static function disk(): Filesystem
    {
        return Storage::disk('lessons');
    }

    public static function maxBytes(): int
    {
        return max(10, (int) Lms::setting('max_video_mb')) * 1024 * 1024;
    }

    /**
     * Stores one chunk. Returns null while chunks are still to come, and the
     * finished video — ['path', 'name', 'size'] — once the last one is in.
     *
     * @throws RuntimeException with a message for the editor when the file is refused
     */
    public static function receive(int $courseId, string $uploadId, int $index, int $total, string $name, UploadedFile $chunk): ?array
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw new RuntimeException('Upload an MP4, WebM, MOV or OGV video.');
        }

        if ($total * self::CHUNK_BYTES > self::maxBytes() + self::CHUNK_BYTES) {
            throw new RuntimeException('This video is larger than the '.Lms::setting('max_video_mb').' MB allowed (Settings → LMS).');
        }

        if ($index === 0) {
            self::purgeStale();
        }

        $dir = "chunks/{$courseId}/{$uploadId}";
        self::disk()->putFileAs($dir, $chunk, (string) $index);

        if (count(self::disk()->files($dir)) < $total) {
            return null;
        }

        return self::assemble($courseId, $dir, $total, $name, $extension);
    }

    private static function assemble(int $courseId, string $dir, int $total, string $name, string $extension): array
    {
        $disk = self::disk();
        $path = $courseId.'/'.Str::random(40).'.'.$extension;
        $target = $disk->path($path);

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        $out = fopen($target, 'wb');
        try {
            for ($i = 0; $i < $total; $i++) {
                $part = $disk->path("{$dir}/{$i}");
                if (! is_file($part)) {
                    throw new RuntimeException('A piece of the upload went missing. Please try again.');
                }
                $in = fopen($part, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
            $disk->deleteDirectory($dir);
        }

        $size = (int) filesize($target);

        // The name says video; make sure the bytes do too.
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($target);
        if (! str_starts_with($mime, 'video/') && $mime !== 'application/mp4') {
            $disk->delete($path);
            throw new RuntimeException('That file is not a video.');
        }

        if ($size > self::maxBytes()) {
            $disk->delete($path);
            throw new RuntimeException('This video is larger than the '.Lms::setting('max_video_mb').' MB allowed (Settings → LMS).');
        }

        return ['path' => $path, 'name' => Str::limit(basename($name), 200, ''), 'size' => $size];
    }

    /** A path this course uploaded and that still exists — nothing else is accepted. */
    public static function belongsTo(int $courseId, ?string $path): bool
    {
        $pattern = '~^'.$courseId.'/[A-Za-z0-9]{40}\.('.implode('|', self::EXTENSIONS).')$~';

        return $path !== null && preg_match($pattern, $path) === 1 && self::disk()->exists($path);
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            self::disk()->delete($path);
        }
    }

    /**
     * Uploads no lesson uses — the lesson modal was cancelled, or the editor
     * closed before saving. Left a day, in case a save is still on its way.
     *
     * @param  array<int, string>  $inUse
     */
    public static function sweep(int $courseId, array $inUse): void
    {
        $disk = self::disk();
        $cutoff = now()->subHours(self::STALE_HOURS)->getTimestamp();

        foreach ($disk->files((string) $courseId) as $file) {
            if (! in_array($file, $inUse, true) && $disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }

    /** Every video of a course, when the course is deleted for good. */
    public static function deleteCourse(int $courseId): void
    {
        self::disk()->deleteDirectory((string) $courseId);
        self::disk()->deleteDirectory("chunks/{$courseId}");
    }

    public static function mime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',
            default => 'video/mp4',
        };
    }

    /** Uploads someone started and never finished. */
    private static function purgeStale(): void
    {
        $disk = self::disk();
        $cutoff = now()->subHours(self::STALE_HOURS)->getTimestamp();

        foreach ($disk->directories('chunks') as $courseDir) {
            foreach ($disk->directories($courseDir) as $uploadDir) {
                $files = $disk->files($uploadDir);
                $newest = $files ? max(array_map(fn ($f) => $disk->lastModified($f), $files)) : 0;
                if ($newest < $cutoff) {
                    $disk->deleteDirectory($uploadDir);
                }
            }
        }
    }
}
