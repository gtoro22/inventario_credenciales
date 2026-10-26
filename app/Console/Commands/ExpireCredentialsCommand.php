<?php

namespace App\Console\Commands;

use App\Models\Credential;
use App\Services\AuditService;
use Illuminate\Console\Command;

class ExpireCredentialsCommand extends Command
{
    protected $signature = 'credentials:expire';

    protected $description = 'Marca como expiradas las credenciales activas cuya fecha de expiración ya pasó.';

    public function handle(AuditService $audit): int
    {
        $count = 0;

        Credential::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->each(function (Credential $credential) use ($audit, &$count) {
                $credential->update(['status' => 'expired']);
                $audit->log('credential.expired', $credential, ['status' => 'active'], ['status' => 'expired']);
                $count++;
            });

        $this->info("Credenciales marcadas como expiradas: {$count}");

        return self::SUCCESS;
    }
}
