<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $user = $this->route('user');
        $id = is_object($user) ? $user->id : $user;
        $creating = $this->isMethod('POST');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$creating ? 'required' : 'nullable', 'string', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'profile_ids' => ['sometimes', 'array'],
            'profile_ids.*' => ['integer', 'exists:profiles,id'],
            'project_ids' => ['sometimes', 'array'],
            'project_ids.*' => ['integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
        ];
    }
}
