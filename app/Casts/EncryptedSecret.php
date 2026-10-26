<?php

namespace App\Casts;

use App\Services\SecretVault;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cifra al escribir y descifra al leer usando SecretVault.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedSecret implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return app(SecretVault::class)->decrypt($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [$key => app(SecretVault::class)->encrypt($value === null ? null : (string) $value)];
    }
}
