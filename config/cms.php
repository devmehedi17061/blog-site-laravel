<?php

/*
|--------------------------------------------------------------------------
| The CMS itself: its version, where updates come from, and this site's pack
|--------------------------------------------------------------------------
| The core (everything in the GitHub repository) is the same on every site
| and is replaced by an update. What makes one site different lives outside
| it: the database (pages, sections, menus, settings …), storage/ and .env,
| and an optional site pack in sites/<name>/ — none of which an update
| touches. See docs/updates-and-site-packs.md.
*/

return [

    // The installed core version: the VERSION file, written by each release.
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',

    'updates' => [
        // owner/repository on GitHub whose Releases carry the update zips.
        'repository' => env('CMS_UPDATE_REPO', 'devmehedi17061/blog-site-laravel'),

        // A token with read access, for a private repository (fine-grained:
        // Contents → Read-only). Can also be set under Settings → Updates.
        'token' => env('CMS_UPDATE_TOKEN'),

        // How often the panel asks GitHub, in hours.
        'check_every' => 6,
    ],

    /*
    | This site's own pack in sites/<name>/ (views that override the core's,
    | its CSS/JS, PHP classes under Sites\<Name>\ and its artisan commands).
    | Empty for a site that is only built in the panel.
    */
    'site_pack' => env('SITE_PACK', ''),

];
