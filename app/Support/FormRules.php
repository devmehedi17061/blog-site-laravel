<?php

namespace App\Support;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Validation\Rule;

/**
 * Turns a built form into Laravel validation rules.
 *
 * The browser's own `required`, `type="email"` and friends are a convenience,
 * never the check — everything a visitor sends is validated again here, from
 * the field definitions rather than from the request.
 */
class FormRules
{
    /** What a visitor may upload through a form's file field. */
    public const UPLOAD_TYPES = 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt';

    /** @return array<string, array<int, mixed>> */
    public static function for(Form $form): array
    {
        $rules = [];

        foreach ($form->fields as $field) {
            $key = 'fields.'.$field->name;

            if ($field->isMultiple()) {
                // The answer is a list, so the list and its members are
                // checked separately.
                $rules[$key] = [$field->required ? 'required' : 'nullable', 'array'];
                $rules[$key.'.*'] = ['string', Rule::in($field->choices())];

                continue;
            }

            $rules[$key] = self::forField($field);
        }

        return $rules;
    }

    /** @return array<int, mixed> */
    private static function forField(FormField $field): array
    {
        $rules = [$field->required ? 'required' : 'nullable'];

        $rules = array_merge($rules, match ($field->type) {
            'email' => ['email:rfc', 'max:255'],
            'url' => ['url', 'max:2000'],
            'tel' => ['string', 'max:40'],
            'number' => ['numeric'],
            'date' => ['date'],
            'textarea' => ['string', 'max:5000'],
            // Documents and pictures only — never anything a server could
            // run (.php, .phtml, .html, .svg …). Both the real content type
            // and the name the visitor gave it must be on the list.
            'file' => ['file', 'max:5120', 'mimes:'.self::UPLOAD_TYPES, 'extensions:'.self::UPLOAD_TYPES],
            'select', 'radio' => ['string', Rule::in($field->choices())],
            // A lone checkbox is a yes/no; "required" means it must be ticked.
            'checkbox' => ['accepted'],
            default => ['string', 'max:255'],
        });

        // A lone checkbox that is not required must still be allowed to be
        // absent, and `nullable` does not cover `accepted`.
        if ($field->isToggle() && ! $field->required) {
            $rules = ['nullable', 'accepted'];
        }

        // Whatever else the author typed in the field's Extra rule box.
        foreach (self::extra($field) as $rule) {
            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * The author's own rules, as a pipe-separated string. Anything that could
     * reach the database or the filesystem is refused — a validation rule is
     * not a place to accept arbitrary strings from a form editor.
     */
    private static function extra(FormField $field): array
    {
        $raw = trim((string) $field->rule);

        if ($raw === '') {
            return [];
        }

        $banned = ['exists', 'unique', 'required_if', 'required_unless', 'same', 'different', 'confirmed'];

        return collect(explode('|', $raw))
            ->map(fn (string $rule) => trim($rule))
            ->filter()
            ->reject(fn (string $rule) => in_array(strtolower(explode(':', $rule)[0]), $banned, true))
            ->values()
            ->all();
    }

    /** Labels the visitor recognises, instead of "fields.email_address". */
    public static function attributes(Form $form): array
    {
        $names = [];

        foreach ($form->fields as $field) {
            $names['fields.'.$field->name] = strtolower($field->label);
            $names['fields.'.$field->name.'.*'] = strtolower($field->label);
        }

        return $names;
    }
}
