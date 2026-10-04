<?php

declare(strict_types=1);

namespace App\Waitlist;

/**
 * La versión del aviso de privacidad que aceptó cada persona sale del TEXTO
 * del aviso, no de una cadena que alguien tiene que acordarse de subir: si el
 * texto cambia, la versión cambia sola y cada inscripción queda atada al texto
 * exacto que se le mostró (el archivo queda en el historial de git).
 */
final class VersionDelConsentimiento
{
    public static function actual(): string
    {
        return self::de((string) file_get_contents(resource_path('views/sitio/legal/privacidad.blade.php')));
    }

    /** Cabe en `consent_version` (32): «sha256-» + 24 hexadecimales. */
    public static function de(string $texto): string
    {
        return 'sha256-'.substr(hash('sha256', $texto), 0, 24);
    }
}
