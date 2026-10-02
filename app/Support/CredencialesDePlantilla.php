<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Impide arrancar en producción con algo que cualquiera que haya leído el
 * repositorio conoce:
 *
 * - la contraseña de base de datos de los archivos versionados
 *   (`.env.example`, `docker-compose.yml`);
 * - la contraseña de plantilla con la que se cifraban los respaldos (estuvo
 *   publicada como valor por omisión; sigue en el historial de git);
 * - el segundo factor apagado (`MFA_ENABLED=false`) o regalado en pantalla
 *   (`MFA_SHOW_CODE=true`): con cualquiera de los dos, una contraseña robada
 *   basta para entrar al panel.
 *
 * Se llama como PRIMERA línea de AppServiceProvider::boot(). Sin clave de
 * aplicación no hay nada que proteger: es el arranque de `composer install`
 * (`package:discover`) en el CI o en el build de la imagen, donde todavía no hay
 * `.env` y APP_ENV cae en «production».
 */
final class CredencialesDePlantilla
{
    /** Contraseñas de base de datos que aparecen en archivos versionados. */
    private const CONTRASENAS_DE_EJEMPLO = ['sistema_pass', 'password', 'secret', 'root'];

    /** Contraseñas de respaldo que estuvieron publicadas como valor por omisión. */
    private const CONTRASENAS_DE_RESPALDO_DE_EJEMPLO = [
        'cambiame-por-algo-seguro-en-produccion',
        'cambiame-por-algo-seguro-en-producción',
    ];

    public static function comprobar(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if (blank(config('app.key'))) {
            return;
        }

        $conexion = (string) config('database.default');
        $contrasena = (string) config("database.connections.{$conexion}.password");

        if (in_array($contrasena, self::CONTRASENAS_DE_EJEMPLO, true)) {
            throw new RuntimeException(
                'La contraseña de la base de datos sigue siendo la del ejemplo (.env.example). '
                .'Cámbiala antes de arrancar en producción.'
            );
        }

        if (in_array((string) config('backup.backup.password'), self::CONTRASENAS_DE_RESPALDO_DE_EJEMPLO, true)) {
            throw new RuntimeException(
                'La contraseña de los respaldos (BACKUP_ARCHIVE_PASSWORD) sigue siendo la de plantilla, '
                .'publicada en el repositorio. Genera una propia antes de arrancar en producción.'
            );
        }

        // Fallo cerrado: un MFA_ENABLED vacío o ilegible cuenta como apagado.
        if (! filter_var(config('mfa.enabled'), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException(
                'El segundo factor está apagado (MFA_ENABLED). En producción tiene que estar encendido.'
            );
        }

        if (filter_var(config('mfa.show_code'), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException(
                'El código del segundo factor se muestra en pantalla (MFA_SHOW_CODE). '
                .'En producción eso equivale a no tener segundo factor.'
            );
        }
    }
}
