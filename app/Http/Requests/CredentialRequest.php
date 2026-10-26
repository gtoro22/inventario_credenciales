<?php

namespace App\Http\Requests;

use App\Enums\CredentialStatus;
use App\Enums\Environment;
use Illuminate\Validation\Rule;

class CredentialRequest extends ApiFormRequest
{
    public function messages(): array
    {
        return ['project_id.exists' => 'El proyecto seleccionado no es válido.'];
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'project_id' => [$creating ? 'required' : 'sometimes', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'credential_type_id' => ['required', 'integer', 'exists:credential_types,id'],
            'criticality_level_id' => ['required', 'integer', 'exists:criticality_levels,id'],
            'name' => ['required', 'string', 'max:150'],
            'username' => ['nullable', 'string', 'max:255'],
            // En edición, vacío/ausente = conservar el secreto actual.
            'secret' => ['nullable', 'string', 'max:20000'],
            'host' => ['nullable', 'string', 'max:500'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'environment' => ['required', Rule::enum(Environment::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(CredentialStatus::class)],
        ];
    }
}
