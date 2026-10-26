<?php

namespace App\Enums;

enum CredentialStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Inactive => 'Inactiva',
            self::Revoked => 'Revocada',
            self::Expired => 'Expirada',
        };
    }
}
