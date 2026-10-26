<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ProjectRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $project = $this->route('project');
        $id = is_object($project) ? $project->id : $project;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('projects', 'code')->ignore($id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'tags' => ['nullable', 'array', 'max:15'],
            'tags.*' => ['string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'El código solo admite letras, números, punto, guion y guion bajo.'];
    }
}
