<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A role and the full set of permissions it holds. The list is the whole
 * truth, not a change: what is left out is taken away.
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role
            ? (bool) $this->user()?->can('update', $role)
            : (bool) $this->user()?->can('create', Role::class);
    }

    public function rules(): array
    {
        $id = $this->route('role')?->id;

        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($id)],
            'description' => ['nullable', 'string', 'max:300'],
            'scope' => ['required', Rule::in(array_keys(Role::SCOPES))],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'slug')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->user()->canGrantPermissions($this->input('permissions', []))) {
                $validator->errors()->add(
                    'permissions',
                    'A role can only be given permissions you hold yourself.'
                );
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Another role already has that name.',
            'permissions.*.exists' => 'One of those permissions does not exist.',
        ];
    }
}
