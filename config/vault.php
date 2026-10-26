<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clave de cifrado de secretos
    |--------------------------------------------------------------------------
    | Los secretos se cifran con AES-256-GCM (cifrado autenticado). Si VAULT_KEY
    | esta vacia se reutiliza APP_KEY. Se recomienda una clave dedicada para
    | poder rotarla sin invalidar las sesiones/cookies de la aplicacion.
    */
    'key' => env('VAULT_KEY') ?: env('APP_KEY'),

    'previous_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('VAULT_PREVIOUS_KEYS', ''))))),

    'cipher' => 'aes-256-gcm',

    /*
    |--------------------------------------------------------------------------
    | Login: proteccion contra fuerza bruta
    |--------------------------------------------------------------------------
    */
    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'base_lockout_seconds' => (int) env('LOGIN_BASE_LOCKOUT_SECONDS', 60),
        'max_lockout_seconds' => (int) env('LOGIN_MAX_LOCKOUT_SECONDS', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auditoria
    |--------------------------------------------------------------------------
    | Claves cuyo valor nunca se escribe en texto plano en los logs.
    */
    'audit_redacted_keys' => ['password', 'secret', 'token', 'remember_token', 'api_key', 'private_key'],

    'expiring_days' => 30,
];
