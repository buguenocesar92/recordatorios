<?php

declare(strict_types=1);

namespace App\Waitlist;

/**
 * Token de un solo uso del doble opt-in: 32 bytes aleatorios en base64url (43
 * caracteres). En la base solo queda su sha256: quien lea la tabla no puede
 * confirmar a nadie.
 */
final class ConfirmationToken
{
    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function looksValid(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1;
    }
}
