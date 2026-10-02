<?php

declare(strict_types=1);

/**
 * Ninguna credencial de los proveedores del MVP entra al repositorio: van en
 * SOPS y de ahí a las variables del despliegue (spec §10, «Fuga de secretos»).
 *
 * Recorre los archivos que git conoce (versionados o por versionar, sin los
 * ignorados) y falla ante algo con forma de token. Las claves de PRUEBA que
 * Cloudflare publica para Turnstile (`1x0000…AA`, `2x0000…AB`…) no tienen forma
 * de secreto real y son las únicas permitidas en `.env.example` y en las pruebas.
 */
/** @return array<string, string> */
function patronesDeSecretos(): array
{
    return [
        'API key de Resend' => '/\bre_[A-Za-z0-9_]{20,}\b/',
        'token de bot de Telegram' => '/\b\d{8,10}:[A-Za-z0-9_-]{35}\b/',
        'clave privada age' => '/AGE-SECRET-KEY-1[0-9A-Z]{50,}/',
        'clave privada PEM' => '/-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----/',
    ];
}

/** @return list<string> */
function archivosDelRepositorio(): array
{
    $salida = [];
    exec('git -C '.escapeshellarg(base_path()).' ls-files --cached --others --exclude-standard 2>/dev/null', $salida, $codigo);

    if ($codigo !== 0) {
        return [];
    }

    return array_values(array_filter(
        $salida,
        fn (string $ruta): bool => ! str_starts_with(basename($ruta), '.sops.') && is_file(base_path($ruta)),
    ));
}

it('no hay credenciales con forma de token en el repositorio', function () {
    $archivos = archivosDelRepositorio();

    if ($archivos === []) {
        $this->markTestSkipped('Sin git disponible en este entorno: el candado corre en el pre-commit y en el equipo.');
    }

    $hallazgos = [];

    foreach ($archivos as $ruta) {
        if (filesize(base_path($ruta)) > 2_000_000) {
            continue;
        }

        $contenido = (string) file_get_contents(base_path($ruta));

        foreach (patronesDeSecretos() as $tipo => $patron) {
            if (preg_match($patron, $contenido) === 1) {
                $hallazgos[] = "{$ruta}: {$tipo}";
            }
        }
    }

    expect($hallazgos)->toBe([], "Posibles secretos:\n".implode("\n", $hallazgos));
});

it('los patrones detectan un secreto de verdad', function (string $muestra) {
    $detectado = collect(patronesDeSecretos())->contains(fn (string $patron): bool => preg_match($patron, $muestra) === 1);

    expect($detectado)->toBeTrue();
})->with([
    'resend' => ['re_'.str_repeat('A1b2', 6)],
    'telegram' => ['123456789:'.str_repeat('Ab', 17).'c'],
    'age' => ['AGE-SECRET-KEY-1'.str_repeat('Q', 58)],
]);
