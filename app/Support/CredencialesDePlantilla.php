<?php

declare(strict_types=1);

namespace App\Support;

use App\Waitlist\FechaDePurgaDeLaLista;
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
 *   basta para entrar al panel;
 * - la sal de la lista de espera vacía o corta: sin ella `email_hash` e
 *   `ip_hash` dejan de ser seudónimos;
 * - Turnstile sin claves o con las de prueba de Cloudflare, o sin hostname
 *   esperado (TURNSTILE_HOSTNAME): sin él se acepta un token resuelto en
 *   cualquier otro sitio con la misma clave;
 * - TRUSTED_PROXIES vacío, con comodín o con rangos anchos;
 * - sin WAITLIST_PURGE_AFTER (la fecha en que se borra lo confirmado).
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
        if (blank(config('app.key'))) {
            return;
        }

        // Staging y cualquier entorno que no sea local/testing: las claves de
        // prueba de Turnstile dejan pasar a cualquier bot.
        if (! app()->environment('production', 'local', 'testing')) {
            self::comprobarTurnstile();
        }

        if (! app()->environment('production')) {
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

        if (strlen((string) config('waitlist.hash_salt')) < 16) {
            throw new RuntimeException(
                'Falta la sal de la lista de espera (WAITLIST_HASH_SALT, al menos 16 caracteres). '
                .'Sin ella, los hash de correo e IP se pueden recalcular desde un correo conocido.'
            );
        }

        self::comprobarTurnstile();

        if (trim((string) config('services.turnstile.hostname')) === '') {
            throw new RuntimeException(
                'Falta TURNSTILE_HOSTNAME (el dominio público de la landing). Sin él, el servidor no comprueba '
                .'en qué sitio se resolvió el desafío y acepta tokens de cualquier otro.'
            );
        }

        /** @var list<string> $proxies */
        $proxies = (array) config('proxies.confiables', []);

        if ($proxies === [] || ! ProxiesDeConfianza::acotadas($proxies)) {
            throw new RuntimeException(
                'TRUSTED_PROXIES vacío o demasiado amplio. En producción va solo la IP (o una subred de /16 o más '
                .'estrecha) del proxy propio: sin ella todos comparten la IP del proxy, y con un comodín o rango ancho '
                .'cualquiera declara su origen (ver config/proxies.php).'
            );
        }

        if (FechaDePurgaDeLaLista::desdeConfiguracion() === null) {
            throw new RuntimeException(
                'Falta WAITLIST_PURGE_AFTER (fecha AAAA-MM-DD: lanzamiento más seis meses). El aviso de privacidad '
                .'promete borrar lo confirmado en ese plazo y sin fecha se conservaría para siempre.'
            );
        }
    }

    private static function comprobarTurnstile(): void
    {
        $secreto = (string) config('services.turnstile.secret');
        $sitekey = (string) config('services.turnstile.sitekey');

        // Las claves de prueba de Cloudflare empiezan por 1x, 2x o 3x seguidas
        // de ceros: con ellas Turnstile no protege nada.
        if ($secreto === '' || $sitekey === '' || preg_match('/^[123]x0{10,}/', $secreto) === 1 || preg_match('/^[123]x0{10,}/', $sitekey) === 1) {
            throw new RuntimeException(
                'Turnstile sin claves propias (TURNSTILE_SITEKEY / TURNSTILE_SECRET): vacías o de prueba. '
                .'Con ellas la lista de espera queda sin anti-bots.'
            );
        }
    }
}
