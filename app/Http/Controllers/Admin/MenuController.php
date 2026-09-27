<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Menu\SaveMenu;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMenuRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\ActivityLog;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * One location's menu, read and written whole.
 *
 * There is no per-item endpoint on purpose: reordering and re-nesting change
 * several rows at once, so saving one row at a time would let a half-applied
 * move leave the menu in a shape the builder never showed anyone.
 */
class MenuController extends Controller
{
    public function __construct(private readonly SaveMenu $saveMenu) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MenuItem::class);

        $location = $request->validate([
            'location' => ['nullable', Rule::in(MenuItem::LOCATIONS)],
        ])['location'] ?? 'Header';

        return MenuItemResource::collection(MenuItem::flat($location));
    }

    public function update(SaveMenuRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();
        $location = $data['location'];

        $this->saveMenu->handle($location, $data['items']);

        ActivityLog::record('Updated menu', $location);

        return MenuItemResource::collection(MenuItem::flat($location));
    }
}
