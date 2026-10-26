<?php

namespace App\Console\Commands;

use App\Services\SecretVault;
use Illuminate\Console\Command;

class GenerateVaultKeyCommand extends Command
{
    protected $signature = 'credentials:generate-key';

    protected $description = 'Genera una clave AES-256 para VAULT_KEY (se imprime; no se escribe en ningún archivo).';

    public function handle(): int
    {
        $this->line('VAULT_KEY='.SecretVault::generateKey());
        $this->comment('Cópiala en tu .env ANTES de guardar secretos. Para rotarla ver `credentials:rotate-key`.');

        return self::SUCCESS;
    }
}
