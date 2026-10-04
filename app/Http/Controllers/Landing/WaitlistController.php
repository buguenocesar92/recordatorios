<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landing;

use App\Enums\ObligationTypeCode;
use App\Http\Controllers\Sitio\PortadaController;
use App\Models\MetricDaily;
use App\Models\WaitlistSignup;
use App\Rules\Turnstile;
use App\Waitlist\ConfirmationToken;
use App\Waitlist\EnlacesDeLaLista;
use App\Waitlist\SignupService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rule;

/**
 * Lista de espera (spec §4, plan 0.3–0.5). Ninguna de estas respuestas abre
 * sesión: los errores se dibujan en la misma respuesta (422) en vez de
 * redirigir con flash, y todas salen con `no-store`.
 *
 * Diferencias con el plan, a propósito y por seguridad:
 * - Confirmar y darse de baja son POST desde una página con un botón, no un
 *   GET que cambia datos: los antivirus de correo y las vistas previas abren
 *   los enlaces solos, y con GET confirmarían inscripciones ajenas (el doble
 *   opt-in dejaría de probar nada) o darían de baja a gente sin querer.
 * - La baja en un clic de los clientes de correo (RFC 8058,
 *   `List-Unsubscribe-Post`) sí es un POST directo a la URL firmada.
 */
final class WaitlistController
{
    public const MENSAJE_GENERICO = 'Si tu correo es válido, te enviamos un enlace para confirmar.';

    public function store(Request $request, SignupService $servicio): Response
    {
        $mensajes = [
            'email.required' => 'Escribe tu correo.',
            'email.email' => 'Ese correo no parece válido. Revisa que tenga la forma nombre@dominio.cl.',
            'email.max' => 'El correo es demasiado largo.',
            'cf-turnstile-response.required' => Turnstile::MENSAJE,
            'cf-turnstile-response.string' => Turnstile::MENSAJE,
            'cf-turnstile-response.max' => Turnstile::MENSAJE,
            'consent.accepted' => 'Para inscribirte tienes que aceptar el aviso de privacidad.',
            'comuna.exists' => 'Elige una comuna de la lista.',
            'interests.*.in' => 'Elige solo obligaciones de la lista.',
            'interests.max' => 'Elige solo obligaciones de la lista.',
        ];

        // Primero lo barato y local. Sin `dns`: la consulta DNS era síncrona,
        // sin tope y corría en cada envío aunque el captcha fuera inválido. El
        // dominio se comprueba en el trabajo de envío (DominioConCorreo).
        $locales = Validator::make($request->all(), [
            'email' => ['required', 'string', 'max:320', 'email:rfc'],
            'consent' => ['accepted'],
            'comuna' => ['nullable', 'string', Rule::exists('comunas', 'code')],
            'interests' => ['nullable', 'array', 'max:5'],
            'interests.*' => ['string', Rule::in(ObligationTypeCode::values())],
            'cf-turnstile-response' => ['required', 'string', 'max:2048'],
        ], $mensajes);

        // Después, y solo si lo anterior pasó, Turnstile (una llamada de red con
        // tope). `required` Y regla implícita: una regla propia no implícita se
        // SALTA cuando el campo no viene, y omitirlo esquivaría Turnstile.
        $validador = $locales->fails() ? $locales : Validator::make($request->all(), [
            'cf-turnstile-response' => ['bail', 'required', new Turnstile($request->ip())],
        ], $mensajes);

        if ($validador->fails()) {
            $errores = $validador->errors();

            // El campo del widget se llama así por Cloudflare; para la página y
            // las pruebas el error es «turnstile».
            if ($errores->has('cf-turnstile-response')) {
                $errores->add('turnstile', (string) $errores->first('cf-turnstile-response'));
                $errores->forget('cf-turnstile-response');
            }

            $bolsa = (new ViewErrorBag)->put('default', $errores);

            return response(PortadaController::renderizar([
                'errors' => $bolsa,
                'anterior' => $request->only(['email', 'comuna', 'interests']),
            ]), 422);
        }

        /** @var array{email: string, comuna?: string|null, interests?: list<string>|null} $datos */
        $datos = $locales->validated();

        $servicio->register(
            (string) $datos['email'],
            isset($datos['comuna']) && $datos['comuna'] !== '' ? (string) $datos['comuna'] : null,
            $datos['interests'] ?? [],
            $request->ip(),
        );

        return response(view('lista.pendiente', ['mensaje' => self::MENSAJE_GENERICO])->render());
    }

    public function confirmShow(string $token): Response
    {
        $signup = $this->buscarPorToken($token);

        return response(view('lista.confirmar', ['token' => $token, 'signup' => $signup])->render());
    }

    public function confirm(string $token): Response
    {
        $signup = $this->buscarPorToken($token);
        $signup->markConfirmed();

        return response(view('lista.confirmada', [
            'encuesta' => EnlacesDeLaLista::encuestaRelativa($signup),
            'baja' => EnlacesDeLaLista::bajaRelativa($signup->id),
        ])->render());
    }

    public function survey(Request $request, WaitlistSignup $signup): Response
    {
        abort_if($signup->confirmed_at === null, 404);
        abort_if($signup->paid_penalty_last_year !== null, 410, 'Ya respondiste, gracias.');

        $respuesta = $request->input('paid_penalty_last_year');
        abort_unless(in_array($respuesta, ['0', '1'], true), 422, 'Elige «Sí» o «No».');

        $signup->forceFill(['paid_penalty_last_year' => $respuesta === '1'])->save();

        return response(view('lista.gracias')->render());
    }

    public function unsubscribeShow(string $id): Response
    {
        return response(view('lista.baja', [
            'accion' => EnlacesDeLaLista::bajaRelativa($id),
        ])->render());
    }

    public function unsubscribe(string $id): Response
    {
        $borradas = WaitlistSignup::query()->whereKey($id)->delete();

        if ($borradas > 0) {
            MetricDaily::incrementar('waitlist_unsubscribed');
        }

        // Misma respuesta exista o no: la baja no sirve para averiguar quién
        // está inscrito.
        return response(view('lista.baja-hecha')->render());
    }

    /**
     * 404 si el token no existe (o no tiene la forma de uno), 410 si ya se usó
     * o venció. La búsqueda es por el sha256 del token, y se compara además en
     * tiempo constante.
     */
    private function buscarPorToken(string $token): WaitlistSignup
    {
        abort_unless(ConfirmationToken::looksValid($token), 404);

        $hash = ConfirmationToken::hash($token);
        $signup = WaitlistSignup::query()->where('confirm_token_hash', $hash)->first();

        abort_if($signup === null || ! hash_equals((string) $signup->confirm_token_hash, $hash), 404);
        abort_if($signup->confirmed_at !== null, 410, 'Este enlace ya se usó.');
        abort_if($signup->confirm_expires_at === null || $signup->confirm_expires_at->isPast(), 410, 'Este enlace venció.');

        return $signup;
    }
}
