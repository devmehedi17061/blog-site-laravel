<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Uploaded files (the public disk, storage/app/public) at /storage/…, for
 * hosts where public/storage — the link `storage:link` makes — is missing:
 * some shared hosts forbid symlinks. Where the link exists the web server
 * hands the file out itself and this is never reached.
 */
class PublicStorageController extends Controller
{
    public function __invoke(string $path): BinaryFileResponse
    {
        $root = realpath(storage_path('app/public'));
        $file = $root ? realpath($root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path)) : false;

        // Only a real file inside the public disk; nothing hidden, no "../".
        abort_unless(
            $file && is_file($file) && str_starts_with($file, $root.DIRECTORY_SEPARATOR)
                && ! str_contains(str_replace('\\', '/', substr($file, strlen($root))), '/.')
                // Never hand out anything that is code rather than a file.
                && ! preg_match('/\.(php\d?|phtml|phar|pht|html?|xml|js|pl|py|cgi|sh)$/i', $file),
            404
        );

        return response()->file($file, [
            'Cache-Control' => 'public, max-age=2592000',
            'X-Content-Type-Options' => 'nosniff',
            // An SVG opened on its own may not run any script it carries.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
