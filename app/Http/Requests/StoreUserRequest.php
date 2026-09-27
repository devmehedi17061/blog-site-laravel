<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use App\Support\Security;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating and editing an account. The field rules are ordinary; the
 * interesting part is after(), which refuses the changes that would lock
 * someone out or hand out more access than the person making them holds.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target
            ? (bool) $this->user()?->can('update', $target)
            : (bool) $this->user()?->can('create', User::class);
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($id)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'status' => ['required', Rule::in(User::STATUSES)],
            // Required for a new account; left blank on an edit, it keeps
            // the current one.
            // Settings → Security decides what a strong enough password is.
            'password' => [$id ? 'nullable' : 'required', 'string', app(Security::class)->passwordRule(), 'max:190'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $actor = $this->user();
            $target = $this->route('user');
            $role = Role::find($this->integer('role_id'));

            if ($target && $actor->is($target)) {
                if ($role?->id !== $target->role_id) {
                    $validator->errors()->add('role_id', 'You cannot change your own role. Ask another admin.');
                }

                if ($this->input('status') !== 'Active') {
                    $validator->errors()->add('status', 'You cannot suspend the account you are signed in with.');
                }

                return;
            }

            if (! $actor->canGrant($role)) {
                $validator->errors()->add('role_id', 'That role has access you do not hold yourself, so you cannot assign it.');
            }

            if ($target && $this->removesLastSuperAdmin($target, $role)) {
                $validator->errors()->add('role_id', 'This is the only active super admin. Make someone else one first.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Another account already uses that email.',
            'role_id.required' => 'Pick a role.',
        ];
    }

    private function removesLastSuperAdmin(User $target, ?Role $role): bool
    {
        if (! $target->isSuperAdmin() || ! $target->isActive()) {
            return false;
        }

        $staysSuperAdmin = $role?->slug === Role::SUPER_ADMIN && $this->input('status') === 'Active';

        return ! $staysSuperAdmin && ! User::otherActiveSuperAdminExists($target);
    }
}
