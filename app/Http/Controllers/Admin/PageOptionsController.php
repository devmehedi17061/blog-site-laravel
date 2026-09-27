<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Section;
use App\Models\SectionTemplate;
use App\Models\User;
use App\Support\PageTemplates;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;

/**
 * Everything the page editor and the section builder need to populate their
 * select boxes and the module palette, in one request.
 */
class PageOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        return response()->json([
            'statuses' => Page::STATUSES,
            'templates' => PageTemplates::options(),
            'layouts' => Page::layoutOptions(),
            'robots' => collect(Seo::ROBOTS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'home_page_id' => Page::homeId(),
            // The two kinds of page section, and the field types a library
            // section may define. Both are contracts the builder honours.
            'modes' => Section::MODES,
            'field_types' => SectionTemplate::FIELD_TYPES,
            'authors' => User::where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'pages' => Page::orderBy('title')->get(['id', 'title', 'slug']),
        ]);
    }
}
