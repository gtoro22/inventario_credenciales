<?php

namespace App\Console\Commands;

use App\Models\Credential;
use App\Services\SecretVault;
use Illuminate\Console\Command;

/**
 * Rotación: 1) mueve la clave vieja a VAULT_PREVIOUS_KEYS y pon la nueva en
 * VAULT_KEY; 2) ejecuta este comando para re-cifrar todo con la clave nueva;
 * 3) cuando termine, retira la clave vieja de VAULT_PREVIOUS_KEYS.
 */
class RotateVaultKeyCommand extends Command
{
    protected $signature = 'credentials:rotate-key {--force : No pedir confirmación}';

    protected $description = 'Re-cifra todos los secretos con la clave actual (VAULT_KEY).';

    public function handle(SecretVault $vault): int
    {
        if (! $this->option('force') && ! $this->confirm('¿Re-cifrar todos los secretos con la clave actual? Haz un respaldo de la base de datos antes.')) {
            return self::FAILURE;
        }

        $done = 0;

        Credential::withTrashed()->whereNotNull('secret')->each(function (Credential $credential) use ($vault, &$done) {
            $raw = $credential->getRawOriginal('secret');
            // Escritura directa: no pasa por el cast (no hay texto plano en memoria innecesario).
            Credential::withTrashed()->whereKey($credential->id)->toBase()->update(['secret' => $vault->reencrypt($raw)]);
            $done++;
        });

        $this->info("Secretos re-cifrados: {$done}");

        return self::SUCCESS;
    }
}
