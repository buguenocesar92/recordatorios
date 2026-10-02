<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Comprueba si una contraseña aparece en una filtración conocida, y falla de
 * forma honesta cuando no puede comprobarlo.
 *
 * POR QUÉ EXISTE ESTA CLASE
 * ─────────────────────────
 * El verificador de fábrica de Laravel («Illuminate\Validation\NotPwnedVerifier»)
 * atrapa el fallo de red, lo manda a «report()», deja el cuerpo de la respuesta
 * vacío y devuelve que la contraseña está limpia. Una respuesta 503 o 429 ni
 * siquiera lanza excepción: el cuerpo queda vacío igual y pasa lo mismo.
 *
 * El resultado es el peor de los dos mundos. En una isla sin salida a internet
 * —que es donde viven los sistemas municipales— la garantía «esta contraseña no
 * está en una filtración conocida» desaparece EN SILENCIO: nadie la comprueba,
 * la pantalla dice que todo está bien y encima cada alta se cuelga hasta 30 s
 * esperando el tiempo de espera por defecto.
 *
 * Acá se elige de forma explícita qué pasa cuando no se puede comprobar, y se
 * escribe en el registro cuál de las dos cosas ocurrió. Ver
 * «config/seguridad.php» → «filtraciones» para el porqué de cada modo.
 *
 * QUÉ SALE DE ACÁ (Ley 21.719)
 * ────────────────────────────
 * Solo los CINCO primeros caracteres del resumen SHA-1 de la contraseña
 * (k-anonymity): quien recibe la consulta ve un prefijo que comparten cientos de
 * miles de contraseñas y no puede saber cuál se preguntó. Nunca sale la
 * contraseña, ni su resumen completo, ni quién la está eligiendo. El registro
 * del degradado tampoco los lleva.
 */
final class VerificadorDeFiltraciones implements UncompromisedVerifier
{
    /** Sin comprobación, la contraseña se rechaza. */
    public const CERRADO = 'cerrado';

    /** Sin comprobación, la contraseña se acepta, pero queda escrito en el registro. */
    public const ABIERTO = 'abierto';

    /** No se comprueba nada y la política no finge que sí. */
    public const APAGADO = 'apagado';

    private const ENDPOINT = 'https://api.pwnedpasswords.com/range/';

    public function __construct(
        public readonly string $modo,
        public readonly int $segundosDeEspera,
    ) {}

    /**
     * El verificador tal como lo dejó configurado quien despliega.
     */
    public static function desdeConfiguracion(): self
    {
        return new self(
            (string) config('seguridad.filtraciones.modo', self::CERRADO),
            max(1, (int) config('seguridad.filtraciones.espera_segundos', 3)),
        );
    }

    /**
     * @param  array{value: mixed, threshold: int|string}  $data
     */
    public function verify($data): bool
    {
        $valor = (string) $data['value'];

        if ($valor === '') {
            return false;
        }

        $resumen = strtoupper(sha1($valor));
        $prefijo = substr($resumen, 0, 5);

        try {
            $respuesta = Http::withHeaders(['Add-Padding' => 'true'])
                ->timeout($this->segundosDeEspera)
                ->get(self::ENDPOINT.$prefijo);

            if (! $respuesta->successful()) {
                // Un 503 o un 429 no lanzan nada, pero tampoco son una
                // comprobación: son la AUSENCIA de una, y hay que tratarlos
                // igual que un fallo de red.
                throw new RuntimeException('El servicio respondió '.$respuesta->status().'.');
            }
        } catch (Throwable $e) {
            return $this->sinComprobar($e->getMessage());
        }

        $umbral = (int) $data['threshold'];

        foreach (explode("\n", trim($respuesta->body())) as $linea) {
            if (! str_contains($linea, ':')) {
                continue;
            }

            [$sufijo, $veces] = explode(':', trim($linea), 2);

            if ($prefijo.$sufijo === $resumen && (int) $veces > $umbral) {
                return false;
            }
        }

        return true;
    }

    /**
     * Qué se hace cuando no hubo respuesta utilizable.
     *
     * Va a «warning» y no a «report()» a propósito: «report()» manda la
     * excepción al manejador de errores, donde se mezcla con el ruido y nadie la
     * mira. Acá queda una línea buscable con el modo y con lo que se decidió,
     * que es lo que necesita ver quien opera el servidor para darse cuenta de
     * que la garantía se apagó sola.
     */
    private function sinComprobar(string $motivo): bool
    {
        $acepta = $this->modo === self::ABIERTO;

        Log::warning(
            'No se pudo comprobar la contraseña contra las listas de filtraciones.',
            [
                'motivo' => $motivo,
                'modo' => $this->modo,
                'espera_segundos' => $this->segundosDeEspera,
                'resultado' => $acepta
                    ? 'se aceptó la contraseña SIN comprobar si está filtrada'
                    : 'se rechazó la contraseña',
            ]
        );

        return $acepta;
    }
}
