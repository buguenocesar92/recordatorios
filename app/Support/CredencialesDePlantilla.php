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
 * - sin WAITLIST_PURGE_AFTER (la fecha en que se borra lo confirmado);
 * - la cookie de sesión sin Secure, ADMIN_VPN_CIDR abierta a Internet o
 *   ilegible, y APP_URL vacía, sin https o local.
 *
 * En staging (todo lo que no sea production/local/testing) se exigen también
 * las claves propias de Turnstile y su hostname.
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
            self::comprobarHostnameDeTurnstile();
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
        self::comprobarHostnameDeTurnstile();

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

        // Fallo cerrado: vacío o ilegible cuenta como «no Secure».
        if (filter_var(config('session.secure'), FILTER_VALIDATE_BOOL) !== true) {
            throw new RuntimeException(
                'SESSION_SECURE_COOKIE no es true. En producción la cookie de sesión del panel tiene que viajar solo por HTTPS.'
            );
        }

        self::comprobarVpn();
        self::comprobarUrlDeLaAplicacion();
    }

    private static function comprobarHostnameDeTurnstile(): void
    {
        if (trim((string) config('services.turnstile.hostname')) === '') {
            throw new RuntimeException(
                'Falta TURNSTILE_HOSTNAME (el dominio público de la landing). Sin él, el servidor no comprueba '
                .'en qué sitio se resolvió el desafío y acepta tokens de cualquier otro.'
            );
        }
    }

    /**
     * Vacía está bien (panel cerrado para todos). Lo que no: una subred que
     * abarque Internet (/0, o más ancha que /8 en IPv4 o /16 en IPv6) o algo
     * que no sea una IP: IpUtils lo trataría como «no coincide» y el error de
     * configuración pasaría inadvertido.
     */
    private static function comprobarVpn(): void
    {
        foreach ((array) config('panel.vpn_cidr', []) as $entrada) {
            [$ip, $prefijo] = array_pad(explode('/', (string) $entrada, 2), 2, null);
            $esV4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
            $esV6 = ! $esV4 && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
            $minimo = $esV4 ? 8 : 16;
            $maximo = $esV4 ? 32 : 128;

            $valida = ($esV4 || $esV6)
                && ($prefijo === null || (ctype_digit($prefijo) && (int) $prefijo >= $minimo && (int) $prefijo <= $maximo));

            if (! $valida) {
                throw new RuntimeException(
                    'ADMIN_VPN_CIDR trae «'.$entrada.'»: abierta a todo Internet, demasiado ancha o no es una subred. '
                    .'Va la subred de WireGuard (p. ej. 10.8.0.0/24).'
                );
            }
        }
    }

    /**
     * Los enlaces de los correos (confirmación, baja) y el dominio canónico
     * salen de APP_URL: vacía, sin https o local, se mandan enlaces rotos o en
     * claro.
     */
    private static function comprobarUrlDeLaAplicacion(): void
    {
        $url = (string) config('app.url');
        $esquema = parse_url($url, PHP_URL_SCHEME);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $locales = ['localhost', '127.0.0.1', '::1', '[::1]', '0.0.0.0'];

        if ($esquema !== 'https' || $host === '' || in_array($host, $locales, true) || str_ends_with($host, '.localhost')) {
            throw new RuntimeException(
                'APP_URL tiene que ser la dirección pública con https (no vacía ni local). De ella salen los enlaces '
                .'de los correos y el dominio canónico.'
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
