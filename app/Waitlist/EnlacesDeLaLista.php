<?php

declare(strict_types=1);

namespace App\Waitlist;

use App\Models\WaitlistSignup;
use Illuminate\Support\Facades\URL;

/**
 * Los enlaces que salen por correo o se dibujan en las páginas de la lista.
 *
 * Se firman RELATIVOS (la firma no depende del host de la petición, que detrás
 * de un proxy puede variar) y se vuelven absolutos con `app.url`, nunca con el
 * host que trae el request.
 */
final class EnlacesDeLaLista
{
    public static function confirmar(string $token): string
    {
        return self::absoluta(route('lista.confirmar', ['token' => $token], false));
    }

    /**
     * Baja en un clic: firmada sobre el id (ULID) de la inscripción, sin
     * vencimiento (sirve desde cualquier correo). Se firma el id y no el
     * `email_hash` para que el seudónimo del correo no viaje en ninguna URL.
     */
    public static function baja(string $id): string
    {
        return self::absoluta(self::bajaRelativa($id));
    }

    public static function bajaRelativa(string $id): string
    {
        return URL::signedRoute('lista.baja', ['id' => $id], absolute: false);
    }

    public static function encuestaRelativa(WaitlistSignup $signup): string
    {
        return URL::temporarySignedRoute(
            'lista.encuesta',
            now()->addMinutes((int) config('waitlist.survey_link_ttl_minutes', 120)),
            ['signup' => $signup->id],
            absolute: false,
        );
    }

    private static function absoluta(string $relativa): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($relativa, '/');
    }
}
