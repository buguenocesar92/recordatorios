<?php

namespace App\Livewire;

use App\Models\User;
use App\Notifications\Auth\MfaCodeNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class VerificarMfa extends Component
{
    /**
     * Cuánto vive el código. Diez minutos alcanzan para ir a buscar el correo y
     * no tanto como para que quede útil si alguien lo intercepta más tarde.
     */
    public const MINUTOS_DE_VIGENCIA = 10;

    public string $code = '';

    public ?string $otpParaMostrar = null;

    /** Adónde se envió el código, sin mostrar la casilla completa en pantalla. */
    public ?string $correoEnmascarado = null;

    /**
     * Si esta pantalla NO envió un código, porque el anterior sigue vigente.
     *
     * La pantalla decía siempre «Hemos generado un código», también cuando no
     * había generado nada nuevo: quien no recibía el primer correo recargaba,
     * no le llegaba nada —correctamente, el anterior seguía sirviendo— y
     * concluía que el sistema está roto.
     */
    public bool $codigoReutilizado = false;

    /**
     * Si esta pantalla acaba de mandar un código nuevo por `reenviar()`.
     *
     * Sin esto, el aviso no tiene forma de distinguir «te mandamos uno nuevo
     * ahora mismo» de los demás estados, y quien pidió otro código no tiene
     * confirmación de que el pedido sirvió de algo.
     */
    public bool $codigoReenviado = false;

    /**
     * Por qué NO se reenvió (tope alcanzado). Va aparte del error de `code`
     * porque lo que falló no fue el código escrito sino el botón de reenvío.
     */
    public ?string $avisoDeReenvio = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->redirect(route('filament.admin.auth.login'));

            return;
        }

        if (! config('mfa.enabled', false)) {
            session()->put('auth.two_factor_verified', true);
            $this->redirectAfterVerification();

            return;
        }

        if (session()->get('auth.two_factor_verified') === true) {
            $this->redirectAfterVerification();

            return;
        }

        $sigueVigente = $user->two_factor_code
            && $user->two_factor_expires_at
            && now()->lte($user->two_factor_expires_at);

        if (! $sigueVigente) {
            // El RateLimiter de verificar() solo frena a quien intenta ADIVINAR
            // el código. Sin este tope acá, quien ya pasó usuario y contraseña
            // puede dejar que el código expire (diez minutos), recargar esta
            // pantalla, y forzar un correo nuevo indefinidamente: la casilla de
            // la víctima queda como buzón de quien está intentando entrar. Es un
            // tope de RECARGA, distinto del que aplica `reenviar()` (pedido
            // explícito por botón).
            $claveEnvio = 'mfa-envio:'.$user->getAuthIdentifier();

            $tope = (int) config('seguridad.codigos_por_ventana', 5);
            $ventana = (int) config('seguridad.ventana_de_codigos_en_minutos', 30) * 60;

            // Apagado en la batería de pruebas: una automatización que entra y
            // sale varias veces agota el tope y se bloquea a sí misma.
            $limitar = (bool) config('seguridad.limitar_intentos', true);

            if ($limitar && RateLimiter::tooManyAttempts($claveEnvio, $tope)) {
                $minutos = (int) ceil(RateLimiter::availableIn($claveEnvio) / 60);

                // El mensaje dice qué hacer, no solo que no se puede. Quien se
                // queda fuera por esto suele ser alguien con un problema de
                // correo, no quien está abusando: sin la salida escrita acá, su
                // única opción es esperar mirando la pantalla.
                $this->addError('code', "Se pidieron demasiados códigos seguidos. Espera {$minutos} minutos, "
                    .'o pídele a quien administra el sistema que te entregue uno con «php artisan panel:codigo-de-rescate».');
                $this->correoEnmascarado = $this->enmascararCorreo((string) $user->email);

                return;
            }

            if ($limitar) {
                RateLimiter::hit($claveEnvio, decaySeconds: $ventana);
            }

            $this->generarYEnviarCodigo($user);
        }

        $this->codigoReutilizado = $sigueVigente;

        $this->correoEnmascarado = $this->enmascararCorreo((string) $user->email);

        if (config('mfa.show_code', false)) {
            $this->otpParaMostrar = $user->two_factor_code;
        }
    }

    /**
     * Genera un código, lo guarda y lo manda. La usan `mount()` (solo si no
     * había uno vigente, y con tope por recarga) y `reenviar()` (siempre,
     * porque pedir otro es justamente invalidar el anterior).
     */
    private function generarYEnviarCodigo(User $user): void
    {
        $user->two_factor_code = (string) random_int(100000, 999999);
        $user->two_factor_expires_at = now()->addMinutes(self::MINUTOS_DE_VIGENCIA);
        $user->save();

        // Se le manda a quien está entrando. Antes acá había un comentario que
        // decía «aquí enviarías tu notificación»: el código se generaba, se
        // guardaba y no salía a ninguna parte. En desarrollo no se notaba
        // porque se muestra en pantalla; con esa opción apagada —como
        // corresponde en producción— el panel pedía un código que no tenía
        // forma de llegarle a nadie, y quedaba cerrado para todos, incluido
        // quien lo instaló.
        //
        // El código NUNCA se escribe en los registros del sistema, ni en
        // desarrollo: quien pueda leer un archivo de registro entraría al
        // panel sin más.
        $user->notify(new MfaCodeNotification((string) $user->two_factor_code, self::MINUTOS_DE_VIGENCIA));
    }

    /**
     * Pide un código nuevo sin esperar a que el anterior venza.
     *
     * Antes, mientras el código de diez minutos siguiera vigente, no había
     * forma de pedir otro salvo recargar la pantalla —y eso, topado por
     * `mfa-envio`—: si el correo se demoraba o caía en spam, la única salida
     * era esperar. Esto invalida el código anterior —deja de servir de
     * inmediato, no solo en pantalla— y manda uno nuevo.
     *
     * El tope de 3 cada 5 minutos por CUENTA (no por IP) es lo que impide que
     * alguien con la contraseña de otro use este botón como una manguera de
     * correos: cada envío ya avisa «alguien está entrando con tu cuenta», así
     * que como mucho le llegan 36 avisos por hora, no miles.
     */
    public function reenviar(): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->redirect(route('filament.admin.auth.login'));

            return;
        }

        // Las mismas guardas que `mount()`: con el segundo factor apagado o ya
        // superado no hay código que pedir, y una acción Livewire de un
        // snapshot vivo no tiene por qué seguir generando códigos y correos.
        if (! config('mfa.enabled', false) || session()->get('auth.two_factor_verified') === true) {
            $this->redirectAfterVerification();

            return;
        }

        $clave = 'mfa-reenvio:'.$user->getAuthIdentifier();

        // Mismo apagado que el resto de los topes de esta pantalla: una
        // automatización que entra y sale varias veces no debe bloquearse a sí
        // misma en local ni en la batería de pruebas.
        $limitar = (bool) config('seguridad.limitar_intentos', true);

        if ($limitar && RateLimiter::tooManyAttempts($clave, maxAttempts: 3)) {
            $minutos = max(1, (int) ceil(RateLimiter::availableIn($clave) / 60));
            $this->avisoDeReenvio = "Se pidieron demasiados códigos seguidos. Espera {$minutos} "
                .Str::plural('minuto', $minutos).' antes de pedir otro.';

            return;
        }

        if ($limitar) {
            RateLimiter::hit($clave, decaySeconds: 300);
        }

        $this->generarYEnviarCodigo($user);

        // El código recién invalidado no tiene por qué seguir en la casilla,
        // ni su error («incorrecto o expirado») bajo ella: se limpia todo y se
        // avisa al navegador para que devuelva el foco a la casilla.
        $this->resetErrorBag();
        $this->code = '';
        $this->avisoDeReenvio = null;
        $this->codigoReutilizado = false;
        $this->codigoReenviado = true;
        $this->dispatch('mfa-reenviado');

        if (config('mfa.show_code', false)) {
            $this->otpParaMostrar = $user->two_factor_code;
        }
    }

    public function verificar(): void
    {
        $this->validate([
            'code' => 'required|string|size:6',
        ], [], ['code' => 'código']);

        $user = Auth::user();

        // Sin un tope de intentos, el código de seis dígitos se puede adivinar a
        // la fuerza dentro de sus diez minutos de vida: son 900.000 valores y
        // quien ya pasó la contraseña puede probarlos sin freno. El límite deja
        // el segundo factor en pie —unos pocos intentos por minuto, no cientos
        // de miles— y se aplica ANTES de comprobar el código, así el bloqueo
        // tampoco deja «colar» un acierto.
        $clave = 'mfa:'.($user?->getAuthIdentifier() ?? 'anonimo');

        if (RateLimiter::tooManyAttempts($clave, maxAttempts: 5)) {
            $segundos = RateLimiter::availableIn($clave);
            $this->addError('code', "Demasiados intentos. Esperá {$segundos} segundos antes de volver a probar.");

            return;
        }

        // hash_equals compara en tiempo constante: no filtra por el tiempo de
        // respuesta cuántos dígitos del código acertó quien está probando.
        if ($user && $user->two_factor_code !== null
            && hash_equals($user->two_factor_code, $this->code)
            && $user->two_factor_expires_at && now()->lt($user->two_factor_expires_at)) {
            RateLimiter::clear($clave);

            session()->put('auth.two_factor_verified', true);

            $user->two_factor_code = null;
            $user->two_factor_expires_at = null;
            $user->save();

            $this->redirectAfterVerification();

            return;
        }

        RateLimiter::hit($clave, decaySeconds: 60);
        $this->addError('code', 'El código ingresado es incorrecto o ha expirado.');
    }

    protected function redirectAfterVerification(): void
    {
        // Adónde se entra después del segundo factor. Estaba escrito a mano como
        // «/dashboard» —un tablero propio que ya se retiró— y, sobre todo,
        // ignoraba «homePath()», que es el único sitio donde el sistema declara
        // adónde va cada persona según su rol. El resultado era que el formulario
        // de acceso respetaba esa decisión y el segundo factor la pisaba: dos
        // caminos distintos hacia dos pantallas distintas para el mismo ingreso.
        $usuario = Auth::user();

        $this->redirect($usuario?->homePath() ?? '/admin');
    }

    /**
     * Deja reconocible la casilla sin exhibirla: «cesar@…» → «ce•••@gmail.com».
     * Confirmar adónde fue el código es útil; publicar la dirección entera en una
     * pantalla que se mira en una oficina compartida, no.
     */
    protected function enmascararCorreo(string $correo): ?string
    {
        if (! str_contains($correo, '@')) {
            return null;
        }

        [$usuario, $dominio] = explode('@', $correo, 2);

        $visible = mb_substr($usuario, 0, min(2, max(1, mb_strlen($usuario) - 1)));

        return $visible.str_repeat('•', 3).'@'.$dominio;
    }

    public function render(): View
    {
        return view('livewire.verificar-mfa')->layout('layouts.app');
    }
}
