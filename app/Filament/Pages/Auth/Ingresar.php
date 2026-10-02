<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * La pantalla de acceso del panel, con un «Recordarme» que recuerda el correo
 * y no la sesión.
 *
 * El de fábrica deja la cookie de recordar de Laravel, que vale catorce meses
 * contra las dos horas de la sesión. Al vencer la sesión esa cookie volvía a
 * autenticar sola, y como la marca del segundo factor vivía en la sesión
 * perdida, el panel saltaba directo a la pantalla del código: con el correo del
 * último que entró a la vista y un código recién enviado a su casilla, sin
 * haber pedido la contraseña ni una vez. El segundo factor quedaba de único
 * factor.
 *
 * Que la cookie ya no entre sola lo resuelve «AcotarVidaDeSesion», que rechaza
 * toda sesión traída por ella. Acá se cierra la otra mitad: no emitirla, y
 * dejar en su lugar lo único que la casilla puede recordar sin abrir una
 * puerta —el correo—, para que quien entra a diario no tenga que escribirlo.
 *
 * La cookie del correo viaja cifrada, como todas las de Laravel, y no sirve
 * para entrar: rellena un campo de un formulario que sigue pidiendo contraseña
 * y segundo factor.
 *
 * Acá se corrige además cómo se cuentan los intentos. El formulario de fábrica
 * cuenta cinco por dirección de origen: en una municipalidad todos salen a
 * internet por la misma, así que cinco peticiones dejaban sin panel a la
 * organización entera. «config/seguridad.php» ya describe la política correcta
 * —por cuenta y por origen a la vez— pero regía solo en «/login», que era la
 * otra puerta, hoy retirada: este formulario es el único que queda y por eso
 * la política vive acá. Dos
 * limitadores INDEPENDIENTES (ninguno de los dos combina cuenta con IP en la
 * misma llave, o el de cuenta deja de cruzar direcciones y el de origen se
 * vuelve más flojo que el de fábrica), y los dos cuentan solo los fallos —un
 * acierto limpia el cupo en vez de gastarlo, o el reingreso normal de toda la
 * oficina tras vencer la sesión de dos horas se comportaría como un ataque.
 */
class Ingresar extends Login
{
    /** Dónde queda el correo recordado. */
    public const COOKIE = 'correo_recordado';

    /**
     * Tres meses. Lo bastante para que quien entra cada semana no lo pierda, y
     * no tanto como para que quede en el equipo de alguien que ya se fue.
     */
    private const DIAS = 90;

    public function mount(): void
    {
        parent::mount();

        $correo = request()->cookie(self::COOKIE);

        if (! is_string($correo) || blank($correo)) {
            return;
        }

        // Se rellena después de «parent::mount()», que es quien vacía el
        // formulario: al revés, el correo recordado se perdía en el mismo acto.
        $this->form->fill([
            'email' => $correo,
            'remember' => true,
        ]);
    }

    public function authenticate(): ?LoginResponse
    {
        $llaveCuenta = static::llaveDeCuenta((string) ($this->data['email'] ?? ''));
        $llaveOrigen = static::llaveDelOrigen();

        if (! $this->puedeSeguirIntentando($llaveCuenta, $llaveOrigen)) {
            return null;
        }

        $recordar = (bool) ($this->data['remember'] ?? false);
        $correo = $this->data['email'] ?? null;

        // La casilla no llega nunca a Laravel: sin esto seguiría emitiendo la
        // cookie de recordar de siempre, y «AcotarVidaDeSesion» cerraría la
        // sesión de esa persona en la petición siguiente.
        $this->data['remember'] = false;

        try {
            $respuesta = parent::authenticate();
        } catch (ValidationException $excepcion) {
            // Solo acá, con la credencial ya comprobada como inválida, se
            // cuenta el intento. Contarlo ANTES —al entrar al método, como
            // hacía la versión anterior— también cuenta los aciertos: una
            // oficina entera reingresando tras vencer la sesión de dos horas
            // se quedaría sin panel sin que nadie se hubiera equivocado.
            $this->contarIntentoFallido($llaveCuenta, $llaveOrigen);

            throw $excepcion;
        }

        // Solo cuando entró de verdad. Si se topó con el desafío de múltiples
        // factores propio de Filament —que este sistema no usa; el segundo
        // factor propio corre después, por el middleware persistente— no hay
        // nada que recordar ni que limpiar todavía.
        if ($respuesta === null) {
            return null;
        }

        // Limpiar y no solo «no contar»: si esta cuenta o este origen tenían
        // fallos previos sin llegar al tope, un acierto los borra. Sin esto, un
        // error de tipeo legítimo seguiría sumando hacia un bloqueo que ya no
        // tiene motivo.
        RateLimiter::clear($llaveCuenta);
        RateLimiter::clear($llaveOrigen);

        if ($recordar && is_string($correo) && filled($correo)) {
            Cookie::queue(self::COOKIE, $correo, self::DIAS * 24 * 60);
        } else {
            Cookie::queue(Cookie::forget(self::COOKIE));
        }

        return $respuesta;
    }

    /**
     * Neutraliza el limitador de fábrica.
     *
     * El padre («Login::authenticate()», en el vendor) llama a
     * «$this->rateLimit(5)» a mano nada más entrar, antes de comprobar
     * ninguna credencial: por herencia, esa llamada resuelve a este método. Ya
     * no hace falta —«authenticate()» decide con sus dos llaves independientes
     * ANTES de invocar al padre—, y dejarlo activo sería un tercer contador
     * fantasma que cuenta lo mismo que el resto sin que nada lo limpie nunca.
     *
     * OJO al tocar esta clase: el vacío rige para TODO el componente, no solo
     * para «authenticate()». Cualquier otra acción que en el futuro llame a
     * «$this->rateLimit(...)» queda neutralizada acá también, en silencio.
     *
     * @param  int  $maxAttempts
     * @param  int  $decaySeconds
     * @param  string|null  $method
     * @param  class-string|null  $component
     */
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void
    {
        // Intencionalmente vacío.
    }

    /**
     * Si la cuenta y el origen todavía pueden intentarlo.
     *
     * Dos limitadores INDEPENDIENTES, cada uno con su propia llave —ni la de
     * cuenta lleva la IP, ni la de origen lleva la cuenta—, igual que ya hace
     * el closure de «POST /login» en «routes/web.php»:
     *
     * - Por cuenta: encarece adivinar una contraseña concreta, sin importar
     *   desde cuántas direcciones se pruebe. Antes del arreglo, la llave
     *   combinaba cuenta e IP, así que cambiar de dirección en cada intento
     *   —el barrido más simple— le abría un cupo nuevo cada vez.
     * - Por origen: ataja a quien prueba cuenta tras cuenta desde un mismo
     *   lugar. Es el mismo tope que trae «/login» —veinte por minuto—, y no
     *   uno más flojo: contar SOLO por origen, con un tope suelto, es
     *   exactamente lo que dejaba a un atacante de una sola IP con cuatro
     *   veces más intentos que el límite de fábrica de Livewire (cinco).
     */
    private function puedeSeguirIntentando(string $llaveCuenta, string $llaveOrigen): bool
    {
        if (! config('seguridad.limitar_intentos', true)) {
            return true;
        }

        $limitadores = [
            $llaveCuenta => (int) config('seguridad.intentos_por_cuenta', 5),
            $llaveOrigen => (int) config('seguridad.intentos_por_origen', 20),
        ];

        foreach ($limitadores as $llave => $tope) {
            if (RateLimiter::tooManyAttempts($llave, $tope)) {
                $this->getRateLimitedNotification(new TooManyRequestsException(
                    static::class,
                    'authenticate',
                    (string) request()->ip(),
                    RateLimiter::availableIn($llave),
                ))?->send();

                return false;
            }
        }

        return true;
    }

    /**
     * Cuenta un intento fallido en los dos limitadores.
     *
     * Se llama solo desde el «catch» de una credencial inválida: es lo que
     * evita que los aciertos —el reingreso normal de toda la oficina tras
     * vencer la sesión— gasten el mismo cupo que las contraseñas mal
     * escritas.
     */
    private function contarIntentoFallido(string $llaveCuenta, string $llaveOrigen): void
    {
        if (! config('seguridad.limitar_intentos', true)) {
            return;
        }

        RateLimiter::hit($llaveCuenta, 60);
        RateLimiter::hit($llaveOrigen, 60);
    }

    /**
     * La llave del tope por origen.
     *
     * Pública para que la batería la pueda limpiar entre pruebas sin repetir
     * acá la forma de construirla, que es justo lo que se desincroniza.
     */
    public static function llaveDelOrigen(?string $ip = null): string
    {
        return 'acceso-panel:origen:'.sha1((string) ($ip ?? request()->ip()));
    }

    /**
     * La llave del tope por cuenta.
     *
     * Sin la IP a propósito: es lo que hace que el cupo sea de verdad por
     * cuenta y no por (cuenta, dirección). El correo se normaliza igual que en
     * el limitador de «/login», o «Ana@…» y «ana@…» serían dos cupos
     * distintos para la misma cuenta.
     */
    public static function llaveDeCuenta(string $correo): string
    {
        return 'acceso-panel:cuenta:'.sha1(strtolower(trim($correo)));
    }
}
