<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cifrado de secretos en reposo (AES-256-GCM, cifrado autenticado).
 *
 * Soporta rotación de claves: se cifra siempre con la clave actual y se
 * intenta descifrar con las claves anteriores si la actual falla.
 */
class SecretVault
{
    private Encrypter $encrypter;

    /**
     * @param  list<string>  $previousKeys
     */
    public function __construct(string $key, array $previousKeys = [], string $cipher = 'aes-256-gcm')
    {
        $this->encrypter = new Encrypter(self::parseKey($key), $cipher);

        if ($previousKeys !== []) {
            $this->encrypter->previousKeys(array_map(fn (string $k) => self::parseKey($k), $previousKeys));
        }
    }

    public static function fromConfig(): self
    {
        $key = config('vault.key');

        if (empty($key)) {
            throw new RuntimeException('No hay clave de cifrado configurada (VAULT_KEY o APP_KEY).');
        }

        return new self($key, config('vault.previous_keys', []), config('vault.cipher', 'aes-256-gcm'));
    }

    public static function generateKey(string $cipher = 'aes-256-gcm'): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey($cipher));
    }

    public function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        return $this->encrypter->encryptString($plain);
    }

    public function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        return $this->encrypter->decryptString($payload);
    }

    /** Re-cifra un payload con la clave actual (útil tras rotar claves). */
    public function reencrypt(?string $payload): ?string
    {
        return $this->encrypt($this->decrypt($payload));
    }

    private static function parseKey(string $key): string
    {
        return Str::startsWith($key, 'base64:') ? base64_decode(Str::after($key, 'base64:')) : $key;
    }
}
