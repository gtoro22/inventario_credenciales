<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Bloqueo progresivo: tras N fallos se bloquea la clave (email + IP) un tiempo
 * que se duplica en cada bloqueo sucesivo hasta un máximo configurable.
 */
class LoginThrottle
{
    public function secondsUntilUnlocked(string $key): int
    {
        $until = (int) Cache::get($this->lockKey($key), 0);

        return max(0, $until - time());
    }

    public function ensureNotLocked(string $key): void
    {
        $seconds = $this->secondsUntilUnlocked($key);

        if ($seconds > 0) {
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos fallidos. Inténtalo de nuevo en {$seconds} segundos.",
            ])->status(429);
        }
    }

    public function hit(string $key): void
    {
        $attempts = (int) Cache::get($this->attemptsKey($key), 0) + 1;
        Cache::put($this->attemptsKey($key), $attempts, now()->addMinutes(15));

        if ($attempts < config('vault.login.max_attempts')) {
            return;
        }

        $lockouts = (int) Cache::get($this->lockoutsKey($key), 0) + 1;
        Cache::put($this->lockoutsKey($key), $lockouts, now()->addDay());
        Cache::forget($this->attemptsKey($key));

        $seconds = min(
            config('vault.login.base_lockout_seconds') * (2 ** ($lockouts - 1)),
            config('vault.login.max_lockout_seconds'),
        );

        Cache::put($this->lockKey($key), time() + $seconds, now()->addSeconds($seconds));
    }

    public function clear(string $key): void
    {
        Cache::forget($this->attemptsKey($key));
        Cache::forget($this->lockoutsKey($key));
        Cache::forget($this->lockKey($key));
    }

    private function attemptsKey(string $key): string
    {
        return 'login:attempts:'.sha1($key);
    }

    private function lockoutsKey(string $key): string
    {
        return 'login:lockouts:'.sha1($key);
    }

    private function lockKey(string $key): string
    {
        return 'login:lock:'.sha1($key);
    }
}
