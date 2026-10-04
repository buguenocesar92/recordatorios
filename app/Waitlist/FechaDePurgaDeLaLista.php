<?php

declare(strict_types=1);

namespace App\Waitlist;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * WAITLIST_PURGE_AFTER leída de forma estricta (AAAA-MM-DD). Ilegible = null:
 * quien la usa decide (la purga no borra nada; el arranque en producción se
 * niega).
 */
final class FechaDePurgaDeLaLista
{
    public static function desdeConfiguracion(): ?Carbon
    {
        $valor = config('waitlist.purge_confirmed_after');

        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        try {
            $fecha = Carbon::createFromFormat('!Y-m-d', $valor);
        } catch (Throwable) {
            return null;
        }

        return $fecha instanceof Carbon && $fecha->format('Y-m-d') === $valor ? $fecha : null;
    }
}
