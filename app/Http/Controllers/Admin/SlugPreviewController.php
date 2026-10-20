<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What the server will turn some text into as a slug — so the editor's
 * address preview and "keyword in the slug" check match what is saved, in
 * any language. Laravel transliterates (Bengali, Arabic, Cyrillic…) where a
 * browser-side guess could only drop the letters.
 */
class SlugPreviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $text = (string) ($request->validate(['text' => ['nullable', 'string', 'max:300']])['text'] ?? '');

        // Pages can be nested (services/sap/training): their slashes stay.
        return response()->json(['slug' => $request->boolean('path') ? \App\Support\Slug::path($text) : Str::slug($text)]);
    }
}
