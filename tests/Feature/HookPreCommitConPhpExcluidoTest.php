<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * El hook `pre-commit` corre PHPStan sólo sobre los `.php` staged que caen en
 * los `paths` de `phpstan.neon`. Cuando TODOS esos archivos están además en
 * `excludePaths` —en el scaffold, el PHP del preset que extiende clases de
 * paquetes que este repo no instala: `app/Data/**`, `HandleInertiaRequests.php`,
 * `TypeScriptTransformerServiceProvider.php`—, PHPStan no analiza nada,
 * imprime «No files found to analyse.» y sale con 1: el MISMO código de salida
 * que «encontré errores». El hook lo tomaba por errores y cancelaba el commit.
 * Medido el 2026-09-11 (tarea 10 de la consola): cada commit de esos archivos
 * obligaba a `--no-verify`, que es justo lo que un hook de calidad no debería
 * enseñar a usar.
 *
 * Este test no lee el hook: lo EJECUTA. Monta un repo git temporal con un
 * `phpstan.neon` mínimo (un `paths`, un archivo en `excludePaths`), le instala
 * el hook real de este repo como `core.hooksPath` y hace `git commit`. Lo que
 * se afirma es el efecto: al terminar, el commit existe (o no).
 *
 * El hook es bash y necesita git. Dentro del contenedor (`make test`) no hay
 * ninguno de los dos, así que ahí se salta —y se ve como saltado, no como
 * verde—; en CI (`ubuntu-latest`) corre entero.
 */

/** Nombre del binario que falta para poder ejecutar el hook, o null si están todos. */
function herramientaQueFaltaParaElHookPreCommit(): ?string
{
    $buscador = new ExecutableFinder;

    foreach (['bash', 'git'] as $binario) {
        if ($buscador->find($binario) === null) {
            return $binario;
        }
    }

    return null;
}

/**
 * Monta el repo temporal: un commit base, `phpstan.neon` con `app` en `paths`
 * y `app/Excluido.php` en `excludePaths`, y el hook de este repo activado.
 *
 * @return string ruta del repo
 */
function repoTemporalParaElHookPreCommit(): string
{
    $repo = sys_get_temp_dir().'/hook-pre-commit-'.bin2hex(random_bytes(6));

    File::makeDirectory($repo.'/app', 0o755, true);
    File::makeDirectory($repo.'/vendor/bin', 0o755, true);

    File::put($repo.'/phpstan.neon', implode("\n", [
        'parameters:',
        '    level: 8',
        '    tmpDir: .phpstan-cache',
        '    paths:',
        '        - app',
        '    excludePaths:',
        '        analyse:',
        '            - app/Excluido.php',
        '',
    ]));

    File::put($repo.'/app/Excluido.php', implode("\n", [
        '<?php',
        '',
        'declare(strict_types=1);',
        '',
        'namespace App;',
        '',
        'final class Excluido',
        '{',
        '    public function saludo(): string',
        '    {',
        '        return \'hola\';',
        '    }',
        '}',
        '',
    ]));

    // Un error de tipos real, nivel 8: el hook tiene que seguir cortándolo.
    File::put($repo.'/app/ConError.php', implode("\n", [
        '<?php',
        '',
        'declare(strict_types=1);',
        '',
        'namespace App;',
        '',
        'final class ConError',
        '{',
        '    public function numero(): int',
        '    {',
        '        return \'no soy un int\';',
        '    }',
        '}',
        '',
    ]));

    // El hook busca `./vendor/bin/phpstan` en el repo que se commitea: se le da
    // el PHPStan de este repo, con el mismo PHP que corre la suite.
    File::put($repo.'/vendor/bin/phpstan', implode("\n", [
        '#!/bin/sh',
        'exec '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('vendor/phpstan/phpstan/phpstan')).' "$@"',
        '',
    ]));
    chmod($repo.'/vendor/bin/phpstan', 0o755);

    File::put($repo.'/.gitignore', ".phpstan-cache/\n");

    foreach ([
        ['git', 'init', '-q', '-b', 'main'],
        ['git', 'config', 'user.name', 'Pest'],
        ['git', 'config', 'user.email', 'pest@example.test'],
        ['git', 'config', 'commit.gpgsign', 'false'],
        ['git', 'config', 'core.hooksPath', base_path('.githooks')],
        ['git', 'add', '.gitignore', 'phpstan.neon', 'vendor/bin/phpstan'],
        ['git', 'commit', '-q', '--no-verify', '-m', 'base'],
    ] as $comando) {
        (new Process($comando, $repo))->mustRun();
    }

    return $repo;
}

/**
 * Stagea `$archivos` y commitea CON el hook puesto.
 *
 * @param  list<string>  $archivos
 * @return array{proceso: Process, commits: int}
 */
function commitearConElHookPreCommit(string $repo, array $archivos): array
{
    (new Process(['git', 'add', ...$archivos], $repo))->mustRun();

    $commit = new Process(['git', 'commit', '-q', '-m', 'prueba del hook'], $repo);
    $commit->run();

    $contar = new Process(['git', 'rev-list', '--count', 'HEAD'], $repo);
    $contar->mustRun();

    return ['proceso' => $commit, 'commits' => (int) trim($contar->getOutput())];
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/hook-pre-commit-*') ?: [] as $directorio) {
        File::deleteDirectory($directorio);
    }
});

beforeEach(function () {
    // Sin el hook no habría nada que ejecutar y el test pasaría vacío.
    expect(is_executable(base_path('.githooks/pre-commit')))->toBeTrue('.githooks/pre-commit no existe o no es ejecutable');
});

it('deja commitear un PHP que phpstan.neon excluye del análisis', function () {
    $repo = repoTemporalParaElHookPreCommit();

    ['proceso' => $commit, 'commits' => $commits] = commitearConElHookPreCommit($repo, ['app/Excluido.php']);

    expect($commit->isSuccessful())->toBeTrue(
        "el hook canceló el commit de un archivo que phpstan.neon excluye:\n".$commit->getOutput().$commit->getErrorOutput()
    )->and($commits)->toBe(2, 'el commit no quedó en el historial');
})->skip(herramientaQueFaltaParaElHookPreCommit() !== null, 'el hook es bash y usa git: sin ellos (p. ej. dentro del contenedor) no se puede ejecutar');

it('sigue cortando el commit cuando el PHP staged tiene un error de tipos', function () {
    $repo = repoTemporalParaElHookPreCommit();

    ['proceso' => $commit, 'commits' => $commits] = commitearConElHookPreCommit($repo, ['app/ConError.php']);

    $salida = $commit->getOutput().$commit->getErrorOutput();

    expect($commit->isSuccessful())->toBeFalse("el hook dejó pasar un error de PHPStan:\n".$salida)
        ->and($commits)->toBe(1, 'el commit con error quedó en el historial')
        ->and(str_contains($salida, 'PHPStan encontró errores'))->toBeTrue("el commit se canceló por otra causa:\n".$salida);
})->skip(herramientaQueFaltaParaElHookPreCommit() !== null, 'el hook es bash y usa git: sin ellos (p. ej. dentro del contenedor) no se puede ejecutar');

it('no deja pasar un error de tipos por venir acompañado de un archivo excluido', function () {
    $repo = repoTemporalParaElHookPreCommit();

    ['proceso' => $commit, 'commits' => $commits] = commitearConElHookPreCommit($repo, ['app/Excluido.php', 'app/ConError.php']);

    expect($commit->isSuccessful())->toBeFalse("el hook dejó pasar un error de PHPStan mezclado con un archivo excluido:\n".$commit->getOutput().$commit->getErrorOutput())
        ->and($commits)->toBe(1, 'el commit con error quedó en el historial');
})->skip(herramientaQueFaltaParaElHookPreCommit() !== null, 'el hook es bash y usa git: sin ellos (p. ej. dentro del contenedor) no se puede ejecutar');

/**
 * Sistema recién generado: hay docker-compose.yml, no hay vendor/ y el
 * contenedor `app` todavía no existe (`make up` no corrió). El hook hacía
 * `docker compose exec app …`, Docker contestaba «service "app" is not
 * running» y el hook lo tomaba por «PHPStan encontró errores»: el primer
 * commit fallaba con un mensaje que culpaba al código. Ahora el hook distingue
 * «no pude analizar» de «analicé y hay errores», lo dice, y no se salta el
 * análisis en silencio: corta salvo que se pida omitirlo con PHPSTAN_HOOK_OMITIR=1.
 *
 * @param  string  $appCorriendo  'si' simula `docker compose ps` con app arriba y `exec` que corre phpstan
 * @return array{repo: string, entorno: array<string, string>}
 */
function repoSinVendorYConDockerSimulado(string $appCorriendo): array
{
    $repo = repoTemporalParaElHookPreCommit();
    unlink($repo.'/vendor/bin/phpstan');
    File::put($repo.'/docker-compose.yml', "services:\n  app:\n    image: x\n");

    $bin = $repo.'/.falso-bin';
    File::makeDirectory($bin, 0o755, true);
    $phpstan = escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('vendor/phpstan/phpstan/phpstan'));
    File::put($bin.'/docker', implode("\n", [
        '#!/bin/sh',
        '# docker compose ps … / docker compose exec -T app vendor/bin/phpstan …',
        'if [ "$2" = "ps" ]; then',
        $appCorriendo === 'si' ? '  echo app; exit 0' : '  exit 0',
        'fi',
        'if [ "$2" = "exec" ]; then',
        $appCorriendo === 'si'
            ? '  shift 5; exec '.$phpstan.' "$@"'
            : '  echo \'service "app" is not running\' >&2; exit 1',
        'fi',
        'exit 0',
        '',
    ]));
    chmod($bin.'/docker', 0o755);
    File::append($repo.'/.gitignore', ".falso-bin/\n");
    (new Process(['git', 'add', '.gitignore', 'docker-compose.yml'], $repo))->mustRun();
    (new Process(['git', 'commit', '-q', '--no-verify', '-m', 'compose'], $repo))->mustRun();

    return ['repo' => $repo, 'entorno' => ['PATH' => $bin.':'.getenv('PATH')]];
}

it('avisa claro que el contenedor no está arriba en vez de culpar al código', function () {
    ['repo' => $repo, 'entorno' => $entorno] = repoSinVendorYConDockerSimulado('no');
    (new Process(['git', 'add', 'app/Excluido.php'], $repo))->mustRun();

    $commit = new Process(['git', 'commit', '-q', '-m', 'primero'], $repo, $entorno);
    $commit->run();
    $salida = $commit->getOutput().$commit->getErrorOutput();

    expect($commit->isSuccessful())->toBeFalse("el hook dejó pasar un commit sin analizar:\n".$salida)
        ->and(str_contains($salida, 'no está levantado'))->toBeTrue("el mensaje no explica que falta levantar el contenedor:\n".$salida)
        ->and(str_contains($salida, 'make up'))->toBeTrue("el mensaje no dice cómo arreglarlo:\n".$salida)
        ->and(str_contains($salida, 'PHPStan encontró errores'))->toBeFalse("culpó al código en vez de al contenedor:\n".$salida);
})->skip(herramientaQueFaltaParaElHookPreCommit() !== null, 'el hook es bash y usa git: sin ellos (p. ej. dentro del contenedor) no se puede ejecutar');

it('deja omitir el análisis sólo si se pide explícito y lo anuncia', function () {
    ['repo' => $repo, 'entorno' => $entorno] = repoSinVendorYConDockerSimulado('no');
    (new Process(['git', 'add', 'app/Excluido.php'], $repo))->mustRun();

    $commit = new Process(['git', 'commit', '-q', '-m', 'primero'], $repo, $entorno + ['PHPSTAN_HOOK_OMITIR' => '1']);
    $commit->run();
    $salida = $commit->getOutput().$commit->getErrorOutput();

    expect($commit->isSuccessful())->toBeTrue("PHPSTAN_HOOK_OMITIR=1 no dejó commitear:\n".$salida)
        ->and(str_contains($salida, 'NO se analizó'))->toBeTrue("la omisión fue silenciosa:\n".$salida);
})->skip(herramientaQueFaltaParaElHookPreCommit() !== null, 'el hook es bash y usa git: sin ellos (p. ej. dentro del contenedor) no se puede ejecutar');

it('con el contenedor arriba sigue analizando por docker compose exec y corta los errores', function () {
    ['repo' => $repo, 'entorno' => $entorno] = repoSinVendorYConDockerSimulado('si');
    (new Process(['git', 'add', 'app/ConError.php'], $repo))->mustRun();

    $commit = new Process(['git', 'commit', '-q', '-m', 'con error'], $repo, $entorno);
    $commit->run();
    $salida = $commit->getOutput().$commit->getErrorOutput();

    expect($commit->isSuccessful())->toBeFalse($salida)
        ->and(str_contains($salida, 'PHPStan encontró errores'))->toBeTrue($salida);
})->skip(herramientaQueFaltaParaElHookPreCommit() !== null, 'el hook es bash y usa git: sin ellos (p. ej. dentro del contenedor) no se puede ejecutar');
