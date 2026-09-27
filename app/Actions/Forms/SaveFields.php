<?php

namespace App\Actions\Forms;

use App\Models\Form;
use App\Models\FormField;
use App\Support\FieldKeys;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a form's whole field list in one transaction.
 *
 * The builder edits everything client side and saves once, so a partial write
 * would leave a form half-built — and a half-built form is one that rejects
 * what visitors send it. Fields are matched by id where the client sends one,
 * which keeps rows stable across an edit.
 */
class SaveFields
{
    public function handle(Form $form, array $fields): Form
    {
        DB::transaction(function () use ($form, $fields) {
            $kept = [];
            $taken = [];

            foreach (array_values($fields) as $position => $input) {
                $kept[] = $this->upsert($form, $input, $position, $taken)->id;
            }

            // Anything the client no longer sends was deleted in the builder.
            $form->fields()->whereNotIn('id', $kept ?: [0])->delete();
        });

        return $form->fresh()->load('fields');
    }

    private function upsert(Form $form, array $input, int $position, array &$taken): FormField
    {
        $type = in_array($input['type'] ?? null, FormField::TYPES, true) ? $input['type'] : 'text';

        // The name becomes the key in every stored payload, so it has to be a
        // safe identifier and unique within the form — the same rule section
        // field keys follow.
        $name = FieldKeys::single($input['name'] ?? '', $input['label'] ?? '', $position, $taken);
        $taken[] = $name;

        $attributes = [
            'type' => $type,
            'label' => trim((string) ($input['label'] ?? '')) ?: 'Untitled field',
            'name' => $name,
            'placeholder' => $input['placeholder'] ?? null,
            'help' => $input['help'] ?? null,
            'width' => in_array($input['width'] ?? null, FormField::WIDTHS, true) ? $input['width'] : 'full',
            'rule' => $input['rule'] ?? null,
            'required' => (bool) ($input['required'] ?? false),
            // Only the types that offer a list keep one, so switching a field
            // to text does not leave stale choices behind it.
            'options' => in_array($type, FormField::CHOICE_TYPES, true)
                ? $this->options($input['options'] ?? [])
                : null,
            'position' => $position,
        ];

        $existing = isset($input['id'])
            ? $form->fields()->whereKey($input['id'])->first()
            : null;

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return $form->fields()->create($attributes);
    }

    private function options(mixed $options): array
    {
        return collect(is_array($options) ? $options : [])
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
