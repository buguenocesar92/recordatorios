<?php

declare(strict_types=1);

/**
 * Candado de probidad (spec §0): este producto de KraftDo SpA no puede contener
 * nada de los sistemas internos de la Municipalidad de Graneros.
 *
 * Recorre el código de la aplicación y falla si aparece cualquiera de los
 * patrones de la lista: nombres de repositorios o sistemas municipales internos,
 * paquetes municipales y accesos internos. La palabra «Graneros» SOLA no falla,
 * y es deliberado: es una de las 346 comunas del catálogo público
 * (database/data/comunas.csv) y se trata igual que las otras 345, solo desde su
 * información pública.
 *
 * Los patrones se arman por partes para que este mismo archivo no se delate.
 */
const DIRECTORIOS_REVISADOS = ['app', 'database', 'resources', 'config', 'routes', 'e2e'];

/** @return list<string> */
function patronesMunicipalesInternos(): array
{
    $g = 'graneros';

    return [
        'atencion'.'vecino',
        'discapacidad-'.$g,
        'licencias-'.$g,
        'seguridad-'.$g,
        'plataforma-'.$g,
        'personas-'.$g,
        'muni'.'-ui',
        'muni'.'-shared',
        '--shared='.'muni',
        $g.'.cl/intranet',
        'ssh '.$g,
    ];
}

/** @return list<string> */
function archivosRevisados(): array
{
    $archivos = [];

    foreach (DIRECTORIOS_REVISADOS as $directorio) {
        $raiz = base_path($directorio);
        if (! is_dir($raiz)) {
            continue;
        }

        $iterador = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
                fn (SplFileInfo $actual): bool => ! in_array($actual->getFilename(), ['node_modules', 'test-results', 'playwright-report', '.auth'], true),
            ),
        );

        foreach ($iterador as $archivo) {
            if ($archivo instanceof SplFileInfo && $archivo->isFile() && $archivo->getSize() < 2_000_000) {
                $archivos[] = $archivo->getPathname();
            }
        }
    }

    return $archivos;
}

it('ningún archivo del código menciona sistemas municipales internos', function () {
    $hallazgos = [];

    foreach (archivosRevisados() as $archivo) {
        $contenido = mb_strtolower((string) file_get_contents($archivo));

        foreach (patronesMunicipalesInternos() as $patron) {
            if (str_contains($contenido, $patron)) {
                $hallazgos[] = str_replace(base_path().'/', '', $archivo)." → «{$patron}»";
            }
        }
    }

    expect($hallazgos)->toBe([], "Referencias a sistemas municipales internos:\n".implode("\n", $hallazgos));
});

it('revisa de verdad: la lista de archivos no está vacía', function () {
    expect(count(archivosRevisados()))->toBeGreaterThan(100);
});

it('el catálogo de comunas cita una fuente pública oficial', function () {
    $ruta = database_path('data/comunas.csv');
    expect(is_file($ruta))->toBeTrue('Falta database/data/comunas.csv');

    $primera = strtok((string) file_get_contents($ruta), "\n");
    expect($primera)->toStartWith('# fuente: https://');

    $host = (string) parse_url(trim(substr((string) $primera, strlen('# fuente: '))), PHP_URL_HOST);

    expect(str_ends_with($host, '.gob.cl') || str_ends_with($host, '.gov.cl'))
        ->toBeTrue("La fuente «{$host}» no es un dominio del Estado (SUBDERE, INE o el servicio público de la DPA)");
});

it('el catálogo trae las 346 comunas con código CUT de cinco dígitos y sin repetir', function () {
    $filas = array_values(array_filter(
        file(database_path('data/comunas.csv'), FILE_IGNORE_NEW_LINES) ?: [],
        fn (string $linea): bool => $linea !== '' && ! str_starts_with($linea, '#') && ! str_starts_with($linea, 'code,'),
    ));

    $codigos = array_map(fn (string $linea): string => explode(',', $linea)[0], $filas);

    expect($filas)->toHaveCount(346)
        ->and(array_unique($codigos))->toHaveCount(346);

    foreach ($filas as $fila) {
        [$codigo, , $region] = str_getcsv($fila, escape: '\\');
        expect($codigo)->toMatch('/^\d{5}$/')
            ->and(substr($codigo, 0, 2))->toBe($region);
    }
});
