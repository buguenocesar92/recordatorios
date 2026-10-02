<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decide si las excepciones se reportan a un colector de errores.
 *
 * Solo se reporta cuando hay un DSN configurado y apunta a un destino propio
 * (un GlitchTip autoalojado), nunca al SaaS de Sentry: una traza puede llevar
 * datos personales en sus variables y no hay contrato con ese tercero.
 */
final class ReporteDeErrores
{
    public static function vaADestinoPropio(): bool
    {
        $dsn = getenv('SENTRY_LARAVEL_DSN');

        if (! is_string($dsn) || trim($dsn) === '') {
            return false;
        }

        $host = parse_url($dsn, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        return $host !== 'sentry.io' && ! str_ends_with($host, '.sentry.io');
    }
}
