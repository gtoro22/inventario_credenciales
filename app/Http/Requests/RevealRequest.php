<?php

namespace App\Http\Requests;

class RevealRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            // Confirmación explícita del usuario antes de exponer el secreto.
            'confirm' => ['required', 'accepted'],
            'purpose' => ['sometimes', 'in:view,copy'],
        ];
    }

    public function messages(): array
    {
        return ['confirm.accepted' => 'Debes confirmar explícitamente que deseas revelar el secreto.'];
    }
}
