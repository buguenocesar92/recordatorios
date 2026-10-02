<?php

declare(strict_types=1);

/**
 * `.env.example` es el contrato de configuración del proyecto, y se comprueba
 * en LOS DOS SENTIDOS.
 *
 * Hacia un lado: cuando una clave que `config()` lee no está en la plantilla, no
 * falla nada. `env()` devuelve null, la configuración cae a su valor por defecto
 * y el sistema arranca haciendo algo distinto de lo que se creía. Así es como
 * `REVERB_APP_KEY` terminó horneada en un bundle, como el tiempo real se apagó
 * sin error y como una CSP quedó en Report-Only en producción: nadie escribió la
 * clave porque nadie sabía que existía.
 *
 * Hacia el otro: una clave que la plantilla PROMETE y que nadie lee es una
 * mentira con la misma cara que una configuración de verdad. Quien despliega la
 * escribe, la revisa en cada incidente y da por hecho que hace algo; el día que
 * el nombre cambió en `config/` y nadie tocó la plantilla, sigue ahí igual de
 * convincente y sin efecto ninguno.
 *
 * DOCUMENTAR NO ES PROMETER, y el candado los trata distinto:
 *   - DOCUMENTADA: el nombre aparece en algún lugar del archivo, aunque sea
 *     dentro de un comentario. Con eso basta para que quien despliegue sepa que
 *     la variable existe, que es de lo que se trata.
 *   - PROMETIDA: hay una línea `NOMBRE=` sin comentar. Eso ya es una afirmación
 *     sobre el comportamiento del sistema, y tiene que ser cierta.
 *
 * Lo que NO se exige: documentar las ~170 variables que solo son valores por
 * defecto del framework (Memcached, SQS, DynamoDB, Beanstalkd, Papertrail…). Un
 * candado que obligue a eso se desactiva a la primera semana. Se exige lo que
 * lee el código de ESTE proyecto, que es lo que decide un despliegue real.
 */

/**
 * Claves que un conjunto de carpetas lee de verdad, con `env('LO_QUE_SEA')`.
 *
 * @param  list<string>  $carpetas
 * @return list<string>
 */
function clavesLeidasEn(array $carpetas): array
{
    $claves = [];

    foreach ($carpetas as $carpeta) {
        $ruta = base_path($carpeta);

        if (! is_dir($ruta)) {
            continue;
        }

        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta));

        foreach ($archivos as $archivo) {
            if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
                continue;
            }

            preg_match_all(
                "/env\(\s*['\"]([A-Z0-9_]+)['\"]/",
                (string) file_get_contents($archivo->getPathname()),
                $encontradas
            );

            $claves = [...$claves, ...$encontradas[1]];
        }
    }

    return array_values(array_unique($claves));
}

/**
 * Lo que lee el código de este proyecto.
 *
 * @return list<string>
 */
function clavesQueLeeElCodigo(): array
{
    return clavesLeidasEn(['config', 'app', 'bootstrap']);
}

/**
 * Lo que leen los archivos de configuración de fábrica de Laravel, que se
 * fusionan con los del proyecto cuando este no los tiene. Por eso `DB_HOST`
 * funciona sin que exista `config/database.php`.
 *
 * @return list<string>
 */
function clavesQueLeeElFramework(): array
{
    return clavesLeidasEn(['vendor/laravel/framework/config']);
}

function plantillaDeEntorno(): string
{
    return (string) file_get_contents(base_path('.'.'env.example'));
}

/**
 * Claves NOMBRADAS en la plantilla: en una línea de configuración o dentro de un
 * comentario, da igual. Nombrarla ya cumple con avisar de que existe.
 *
 * @return list<string>
 */
function clavesNombradasEnLaPlantilla(): array
{
    preg_match_all('/\b([A-Z][A-Z0-9]*(?:_[A-Z0-9]+)+)\b/', plantillaDeEntorno(), $encontradas);

    return array_values(array_unique($encontradas[1]));
}

/**
 * Claves PROMETIDAS: línea `NOMBRE=` sin comentar. La plantilla afirma que
 * escribir ahí cambia algo.
 *
 * @return list<string>
 */
function clavesPrometidasPorLaPlantilla(): array
{
    preg_match_all('/^\s*([A-Z0-9_]+)\s*=/m', plantillaDeEntorno(), $encontradas);

    return array_values(array_unique($encontradas[1]));
}

it('la plantilla nombra todas las claves que el código de este proyecto lee', function () {
    $faltantes = array_values(array_diff(
        clavesQueLeeElCodigo(),
        clavesNombradasEnLaPlantilla(),
    ));

    sort($faltantes);

    expect($faltantes)->toBe([], implode("\n", [
        'Estas claves las lee `config()` y NO están en la plantilla, así que quien despliegue',
        'no sabe que existen y el sistema arranca con el valor por defecto en silencio.',
        'Basta con nombrarlas en un comentario si no hay que tocarlas nunca:',
        ...array_map(static fn (string $c): string => "  - {$c}", $faltantes),
    ]));
});

it('la plantilla no promete ninguna clave que nadie lea', function () {
    // Se aceptan las de fábrica del framework: `DB_PASSWORD` no aparece en
    // ningún `config/` de este proyecto y sin embargo hace exactamente lo que
    // promete, porque la lee el `config/database.php` que Laravel fusiona.
    $huerfanas = array_values(array_diff(
        clavesPrometidasPorLaPlantilla(),
        clavesQueLeeElCodigo(),
        clavesQueLeeElFramework(),
    ));

    sort($huerfanas);

    expect($huerfanas)->toBe([], implode("\n", [
        'La plantilla promete estas claves —línea `NOMBRE=`, sin comentar— y NADIE las lee:',
        ...array_map(static fn (string $c): string => "  - {$c}", $huerfanas),
        '',
        'Una variable que no hace nada es peor que una ausente: quien despliega la escribe,',
        'la revisa en cada incidente y da por hecho que surte efecto. Si el nombre cambió en',
        '`config/`, hay que cambiarlo acá; si ya no se usa, se borra o se pasa a comentario.',
    ]));
});
