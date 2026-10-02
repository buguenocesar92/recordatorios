<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Un proyecto generado tiene que nacer con SUS nombres de contenedor, volumen y
 * red, nunca con los del scaffold.
 *
 * `container_name` y los `name:` de volumen son globales en la máquina, no por
 * proyecto compose. El generador los renombraba con `sed` sobre literales
 * (`scaffold-app-`, `mariadb_scaffold_data`, `scaffold_storage`), pero el
 * compose pasó a nombrarse con el repo entero (`<repo>-app-*`) y los literales
 * dejaron de calzar sin que nada fallara: medido el 2026-09-13, `./scaffold new`
 * dejaba los cuatro `container_name`, el volumen de MariaDB y el de storage de
 * producción con los nombres del scaffold. Al levantarlo chocaba con el
 * checkout del scaffold o, peor, montaba el MISMO volumen de la base.
 *
 * Este test corre la parametrización REAL (`tools/parametrizar-identidad.php`,
 * la que llama el generador) sobre una copia de los composes de este repo, y
 * compara contra los nombres que esos composes declaran HOY: no hay literales
 * que puedan volver a quedar desfasados. Por la misma razón sigue siendo válido
 * cuando viaja a un proyecto generado, cuyo compose ya lleva otro prefijo.
 */
const SLUG_DE_PRUEBA = 'proyecto-de-prueba-generado';

/** Archivos que declaran o usan nombres globales de Docker. */
const ARCHIVOS_CON_NOMBRES_DOCKER = ['docker-compose.yml', 'docker-compose.prod.yml', 'test-all.sh'];

/**
 * Nombres que Docker trata como globales (o que identifican al proyecto) en los
 * dos composes de un directorio.
 *
 * @return array{contenedores: list<string>, volumenes: list<string>, redes: list<string>, proyecto: list<string>}
 */
function nombresDockerDe(string $directorio): array
{
    $nombres = ['contenedores' => [], 'volumenes' => [], 'redes' => [], 'proyecto' => []];

    foreach (['docker-compose.yml', 'docker-compose.prod.yml'] as $archivo) {
        $compose = Yaml::parseFile($directorio.'/'.$archivo, Yaml::PARSE_CUSTOM_TAGS);

        if (isset($compose['name'])) {
            $nombres['proyecto'][] = (string) $compose['name'];
        }

        foreach ($compose['services'] ?? [] as $definicion) {
            if (is_array($definicion) && isset($definicion['container_name'])) {
                $nombres['contenedores'][] = (string) $definicion['container_name'];
            }
        }

        foreach (['volumes' => 'volumenes', 'networks' => 'redes'] as $seccion => $destino) {
            foreach ($compose[$seccion] ?? [] as $clave => $definicion) {
                $nombres[$destino][] = (string) $clave;

                if (is_array($definicion) && isset($definicion['name'])) {
                    $nombres[$destino][] = (string) $definicion['name'];
                }
            }
        }
    }

    return array_map(static fn (array $lista): array => array_values(array_unique($lista)), $nombres);
}

/**
 * Copia los archivos con nombres Docker a un directorio temporal y corre sobre
 * él la parametrización real.
 *
 * @param  callable(string): void|null  $alterar  para estropear la copia antes de parametrizar
 * @return array{directorio: string, proceso: Process}
 */
function parametrizarCopiaDeIdentidad(?callable $alterar = null): array
{
    $directorio = sys_get_temp_dir().'/scaffold-identidad-'.uniqid();
    File::makeDirectory($directorio, 0o755, true);

    foreach (ARCHIVOS_CON_NOMBRES_DOCKER as $archivo) {
        copy(base_path($archivo), $directorio.'/'.$archivo);
    }

    if ($alterar !== null) {
        $alterar($directorio);
    }

    $proceso = new Process([PHP_BINARY, base_path('tools/parametrizar-identidad.php'), $directorio, SLUG_DE_PRUEBA]);
    $proceso->run();

    return ['directorio' => $directorio, 'proceso' => $proceso];
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/scaffold-identidad-*') ?: [] as $directorio) {
        File::deleteDirectory($directorio);
    }
});

it('el proyecto generado no hereda ningún contenedor, volumen ni red del scaffold', function () {
    $delScaffold = nombresDockerDe(base_path());

    // Sin nombres que comparar, el candado pasaría en verde sin vigilar nada.
    expect($delScaffold['contenedores'])->not->toBeEmpty('los composes ya no declaran container_name')
        ->and($delScaffold['volumenes'])->not->toBeEmpty('los composes ya no declaran volúmenes');

    ['directorio' => $directorio, 'proceso' => $proceso] = parametrizarCopiaDeIdentidad();

    expect($proceso->isSuccessful())->toBeTrue('parametrizar-identidad.php falló: '.$proceso->getErrorOutput().$proceso->getOutput());

    $generados = nombresDockerDe($directorio);

    foreach ($delScaffold as $tipo => $nombres) {
        $heredados = array_values(array_intersect($nombres, $generados[$tipo]));

        expect($heredados)->toBe([], "el proyecto generado conserva {$tipo} del scaffold: ".implode(', ', $heredados));
    }

    foreach ($generados['contenedores'] as $contenedor) {
        expect($contenedor)->toStartWith(SLUG_DE_PRUEBA.'-app-');
    }

    foreach ($generados['volumenes'] as $volumen) {
        expect($volumen)->toContain(SLUG_DE_PRUEBA);
    }

    // Y por texto: un nombre del scaffold que sobreviva en un comentario de
    // puertos, en `test-all.sh` o en una referencia de servicio también delata
    // que el reemplazo no alcanzó a todos los usos.
    $nombresDelScaffold = array_merge(...array_values($delScaffold));

    foreach (ARCHIVOS_CON_NOMBRES_DOCKER as $archivo) {
        $contenido = (string) file_get_contents($directorio.'/'.$archivo);

        foreach ($nombresDelScaffold as $nombre) {
            expect(str_contains($contenido, $nombre))->toBeFalse("{$archivo} generado sigue usando «{$nombre}», del scaffold");
        }
    }
});

it('renombrar la identidad no toca los puertos ni la dirección a la que se publican', function () {
    // El bind a loopback (e2f4e61) y el cambio de puerto son de otro paso del
    // generador: este script solo cambia nombres.
    ['directorio' => $directorio, 'proceso' => $proceso] = parametrizarCopiaDeIdentidad();

    expect($proceso->isSuccessful())->toBeTrue($proceso->getErrorOutput());

    foreach (['docker-compose.yml', 'docker-compose.prod.yml'] as $archivo) {
        $original = Yaml::parseFile(base_path($archivo), Yaml::PARSE_CUSTOM_TAGS);
        $generado = Yaml::parseFile($directorio.'/'.$archivo, Yaml::PARSE_CUSTOM_TAGS);

        foreach ($original['services'] as $servicio => $definicion) {
            expect($generado['services'][$servicio]['ports'] ?? null)->toEqual($definicion['ports'] ?? null);
        }
    }
});

it('muere sin tocar nada si los contenedores no siguen el esquema <prefijo>-app-<servicio>', function () {
    // Sin prefijo que deducir, «Parametrizando proyecto…» no puede seguir de
    // largo: es exactamente el fallo silencioso que originó este test.
    ['directorio' => $directorio, 'proceso' => $proceso] = parametrizarCopiaDeIdentidad(
        static function (string $directorio): void {
            $compose = $directorio.'/docker-compose.yml';
            file_put_contents($compose, (string) preg_replace(
                '/container_name:\s*\S+/',
                'container_name: sin-esquema',
                (string) file_get_contents($compose),
            ));
        },
    );

    expect($proceso->isSuccessful())->toBeFalse()
        ->and($proceso->getErrorOutput())->toContain('container_name')
        ->and((string) file_get_contents($directorio.'/docker-compose.prod.yml'))
        ->toBe((string) file_get_contents(base_path('docker-compose.prod.yml')));
});
