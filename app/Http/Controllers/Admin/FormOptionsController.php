<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\JsonResponse;

/**
 * Everything the forms list and the builder need to fill their select boxes,
 * in one request.
 */
class FormOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', Form::class);

        return response()->json([
            'statuses' => Form::STATUSES,
            'field_types' => FormField::TYPES,
            'choice_types' => FormField::CHOICE_TYPES,
            'widths' => FormField::WIDTHS,
        ]);
    }
}
