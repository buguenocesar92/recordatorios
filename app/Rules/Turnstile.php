<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifica el token de Cloudflare Turnstile del lado del servidor (siteverify).
 *
 * Falla CERRADO: sin token, con token de más de 2048 caracteres, con Cloudflare
 * caído o lento (timeout de 5 s), con una respuesta ilegible o con
 * `success: false`, la inscripción no pasa. La URL y la clave secreta salen de
 * config/services.php, nunca del request (no hay SSRF posible). A los registros
 * va solo el código de error de Cloudflare, nunca el token ni la IP.
 */
final class Turnstile implements ValidationRule
{
    /**
     * Implícita: corre aunque el campo no venga o venga vacío. Sin esto Laravel
     * omite la regla para un campo ausente y bastaría con no mandarlo.
     */
    public bool $implicit = true;

    public const MENSAJE = 'No pudimos comprobar que no eres un robot. Recarga la página e inténtalo de nuevo (necesita JavaScript activado).';

    public function __construct(private readonly ?string $ip = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2048) {
            $fail(self::MENSAJE);

            return;
        }

        // Sin secreto no hay verificación posible: se rechaza en vez de preguntarle
        // a Cloudflare con una clave vacía.
        if ((string) config('services.turnstile.secret') === '') {
            Log::error('turnstile: sin clave secreta configurada');
            $fail(self::MENSAJE);

            return;
        }

        try {
            $respuesta = Http::asForm()
                ->timeout((int) config('services.turnstile.timeout', 5))
                ->post((string) config('services.turnstile.url'), array_filter([
                    'secret' => (string) config('services.turnstile.secret'),
                    'response' => $value,
                    'remoteip' => $this->ip,
                ]));
        } catch (ConnectionException) {
            Log::warning('turnstile: sin respuesta de siteverify');
            $fail(self::MENSAJE);

            return;
        } catch (Throwable) {
            $fail(self::MENSAJE);

            return;
        }

        $datos = $respuesta->json();

        if (! $respuesta->successful() || ! is_array($datos) || ($datos['success'] ?? false) !== true) {
            $codigos = is_array($datos) ? implode(',', (array) ($datos['error-codes'] ?? [])) : 'ilegible';
            Log::info('turnstile: rechazado', ['codigos' => $codigos]);
            $fail(self::MENSAJE);

            return;
        }

        $esperado = (string) config('services.turnstile.hostname');

        if ($esperado !== '' && ($datos['hostname'] ?? null) !== $esperado) {
            Log::info('turnstile: hostname distinto');
            $fail(self::MENSAJE);
        }
    }
}
