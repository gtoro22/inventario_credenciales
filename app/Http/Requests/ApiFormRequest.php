<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Base: la autorización fina vive en Policies/middleware, no aquí. */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
