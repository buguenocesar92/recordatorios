<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * El hook `pre-push` corre la suite antes de publicar en `develop`, y sólo ahí.
 * Dos defectos, reproducidos el 2026-09-14 con `git push origin --delete <rama>`
 * desde el checkout en `develop` y los contenedores arriba:
 *
 * 1. Decidía por la RAMA ACTUAL (`git rev-parse --abbrev-ref HEAD`), no por lo
 *    que se envía. Git le pasa por stdin una línea por ref
 *    (`<local_ref> <local_sha> <remote_ref> <remote_sha>`); borrar una rama
 *    remota no publica código (sha local en ceros) y aun así corría la suite.
 * 2. `make test` corre `docker compose exec` sin `-T`. Dentro de un hook stdin
 *    es la tubería de refs, no una terminal, y docker se niega con «cannot
 *    attach stdin to a TTY-enabled container»: el hook cancelaba TODO envío a
 *    `develop` con contenedores arriba, o sea el caso que quería proteger.
 *
 * Este test no lee el hook: lo EJECUTA con un `git push` real hacia un remoto
 * bare en disco. En el repo temporal va el Makefile REAL de este repo (el
 * contrato hook↔Makefile es lo que se prueba) y un `docker` falso en el PATH
 * que responde `app` a `compose ps` y, en `compose exec`, exige `-T` cuando
 * stdin no es una terminal —exactamente como el docker de verdad—, deja un
 * marcador de «la suite se invocó» y sale con el código que el test le pida.
 * El wrapper V2 y su hook previo se copian al escenario con un escáner
 * sintético y su pin SHA-256: nunca dependen del escáner externo del host
 * ni desactivan el gate para conseguir un resultado verde.
 *
 * El hook es bash y necesita git y make. Dentro del contenedor (`make test`)
 * no hay bash ni git, así que ahí se salta —y se ve como saltado, no como
 * verde—; en CI (`ubuntu-latest`) corre entero.
 */

/** Nombre del binario que falta para poder ejecutar el hook, o null si están todos. */
function herramientaQueFaltaParaElHookPrePush(): ?string
{
    $buscador = new ExecutableFinder;

    foreach (['bash', 'git', 'make'] as $binario) {
        if ($buscador->find($binario) === null) {
            return $binario;
        }
    }

    return null;
}

/**
 * Monta un remoto bare y un checkout con el Makefile y los hooks reales,
 * Docker simulado y un escáner sintético con su propio pin válido.
 *
 * @return array{repo: string, remoto: string, base: string}
 */
function escenarioParaElHookPrePush(): array
{
    $raiz = sys_get_temp_dir().'/hook-pre-push-'.bin2hex(random_bytes(6));
    $repo = $raiz.'/repo';
    $remoto = $raiz.'/remoto.git';

    File::makeDirectory($repo.'/bin', 0o755, true);
    File::makeDirectory($repo.'/.githooks', 0o755, true);
    File::makeDirectory($remoto, 0o755, true);

    File::copy(base_path('Makefile'), $repo.'/Makefile');

    foreach (['pre-push', 'pre-push.xeon-strix-original'] as $hook) {
        File::copy(base_path('.githooks/'.$hook), $repo.'/.githooks/'.$hook);
        chmod($repo.'/.githooks/'.$hook, 0o755);
    }

    File::put($repo.'/bin/strix-guard', implode("\n", [
        '#!/bin/sh',
        'set -eu',
        '[ "$#" -eq 3 ] && [ "$1" = "secreto" ] || exit 2',
        '[ "$3" = '.escapeshellarg($repo).' ] || exit 2',
        'printf "%s\\n" "$2" >> "$3/escaneos"',
        'if [ -f "$3/sha-rechazado" ] && [ "$2" = "$(cat "$3/sha-rechazado")" ]; then',
        '    echo "escáner sintético rechazó la ref" >&2',
        '    exit 42',
        'fi',
        'resultado="$(cat "$3/resultado-escaner" 2>/dev/null || echo 0)"',
        '[ "$resultado" -eq 0 ] || echo "escáner sintético falló" >&2',
        'exit "$resultado"',
        '',
    ]));
    chmod($repo.'/bin/strix-guard', 0o755);
    File::put($repo.'/.githooks/strix-guard.sha256', hash_file('sha256', $repo.'/bin/strix-guard')."\n");

    // Emula lo justo de `docker compose`: `ps` dice que `app` está arriba;
    // `exec` deja el marcador, exige -T sin terminal y sale con el código
    // guardado en `resultado-suite` (0 por defecto).
    File::put($repo.'/bin/docker', implode("\n", [
        '#!/bin/sh',
        'case "$1 $2" in',
        '    "compose ps") echo app; exit 0 ;;',
        '    "compose exec")',
        '        shift 2',
        '        : > '.escapeshellarg($repo.'/suite-invocada'),
        '        con_t=0',
        '        for arg in "$@"; do [ "$arg" = "-T" ] && con_t=1; done',
        '        if [ "$con_t" -eq 0 ] && ! [ -t 0 ]; then',
        '            echo "cannot attach stdin to a TTY-enabled container because stdin is not a terminal" >&2',
        '            exit 1',
        '        fi',
        '        echo "suite simulada"',
        '        exit "$(cat '.escapeshellarg($repo.'/resultado-suite').' 2>/dev/null || echo 0)"',
        '        ;;',
        '    *) echo "docker falso: no sé hacer $*" >&2; exit 2 ;;',
        'esac',
        '',
    ]));
    chmod($repo.'/bin/docker', 0o755);

    (new Process(['git', 'init', '-q', '--bare', '-b', 'main'], $remoto))->mustRun();

    foreach ([
        ['git', 'init', '-q', '-b', 'develop'],
        ['git', 'config', 'user.name', 'Pest'],
        ['git', 'config', 'user.email', 'pest@example.test'],
        ['git', 'config', 'commit.gpgsign', 'false'],
        ['git', 'remote', 'add', 'origin', $remoto],
        ['git', 'add', 'Makefile'],
        ['git', 'commit', '-q', '-m', 'base'],
        ['git', 'push', '-q', 'origin', 'develop', 'develop:refs/heads/feat/borrable'],
        ['git', 'config', 'core.hooksPath', $repo.'/.githooks'],
    ] as $comando) {
        (new Process($comando, $repo))->mustRun();
    }

    $base = new Process(['git', 'rev-parse', 'HEAD'], $repo);
    $base->mustRun();

    return ['repo' => $repo, 'remoto' => $remoto, 'base' => trim($base->getOutput())];
}

/** Deja un commit nuevo en la rama actual del repo temporal y devuelve su sha. */
function commitNuevoParaElHookPrePush(string $repo): string
{
    File::put($repo.'/cambio.txt', bin2hex(random_bytes(4))."\n");

    foreach ([
        ['git', 'add', 'cambio.txt'],
        ['git', 'commit', '-q', '-m', 'un cambio'],
    ] as $comando) {
        (new Process($comando, $repo))->mustRun();
    }

    $sha = new Process(['git', 'rev-parse', 'HEAD'], $repo);
    $sha->mustRun();

    return trim($sha->getOutput());
}

/**
 * Hace un push real con ambos gates activos y dependencias sintéticas aisladas.
 *
 * @param  list<string>  $args
 */
function pushConElHookPrePush(string $repo, array $args): Process
{
    $push = new Process(['git', 'push', 'origin', ...$args], $repo, [
        'PATH' => $repo.'/bin:'.(getenv('PATH') ?: '/usr/bin:/bin'),
        'SKIP_PREPUSH_TESTS' => '0',
        'STRIX_GUARD_HOOK_ACTIVADO' => '1',
        'STRIX_GUARD_MOTIVO' => false,
        'STRIX_GUARD_BIN' => $repo.'/bin/strix-guard',
        'STRIX_GUARD_PIN' => $repo.'/.githooks/strix-guard.sha256',
    ]);
    $push->run();

    return $push;
}

/** Sha al que apunta `$rama` en el remoto, o null si no existe. */
function shaEnElRemotoDelHookPrePush(string $remoto, string $rama): ?string
{
    $listar = new Process(['git', 'rev-parse', '--verify', '-q', 'refs/heads/'.$rama], $remoto);
    $listar->run();

    return $listar->isSuccessful() ? trim($listar->getOutput()) : null;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/hook-pre-push-*') ?: [] as $directorio) {
        File::deleteDirectory($directorio);
    }
});

beforeEach(function () {
    // Sin el hook no habría nada que ejecutar y el test pasaría vacío.
    expect(is_executable(base_path('.githooks/pre-push')))->toBeTrue('.githooks/pre-push no existe o no es ejecutable');
});

it('borra una rama remota desde develop sin correr la suite', function () {
    ['repo' => $repo, 'remoto' => $remoto] = escenarioParaElHookPrePush();

    $push = pushConElHookPrePush($repo, ['--delete', 'feat/borrable']);
    $salida = $push->getOutput().$push->getErrorOutput();

    expect($push->isSuccessful())->toBeTrue("el hook canceló el borrado de una rama remota:\n".$salida)
        ->and(file_exists($repo.'/suite-invocada'))->toBeFalse("el hook corrió la suite para un borrado, que no publica código:\n".$salida)
        ->and(shaEnElRemotoDelHookPrePush($remoto, 'feat/borrable'))->toBeNull('la rama sigue en el remoto')
        ->and(file_exists($repo.'/escaneos'))->toBeFalse('un borrado no publica código para escanear');
})->skip(herramientaQueFaltaParaElHookPrePush() !== null, 'el hook es bash y usa git y make: sin ellos (p. ej. dentro del contenedor) no se puede ejecutar');

it('publica commits en develop con la suite en verde aunque stdin no sea una terminal', function () {
    ['repo' => $repo, 'remoto' => $remoto, 'base' => $base] = escenarioParaElHookPrePush();
    $sha = commitNuevoParaElHookPrePush($repo);

    $push = pushConElHookPrePush($repo, ['develop']);
    $salida = $push->getOutput().$push->getErrorOutput();

    expect(file_exists($repo.'/suite-invocada'))->toBeTrue("el hook no corrió la suite al publicar en develop:\n".$salida)
        ->and(str_contains($salida, 'cannot attach stdin'))->toBeFalse("docker compose exec se invocó sin -T dentro del hook:\n".$salida)
        ->and($push->isSuccessful())->toBeTrue("el hook canceló un envío a develop con la suite en verde:\n".$salida)
        ->and(shaEnElRemotoDelHookPrePush($remoto, 'develop'))->toBe($sha, 'develop no se actualizó en el remoto')
        ->and(File::get($repo.'/escaneos'))->toBe($base.'..'.$sha."\n");
})->skip(herramientaQueFaltaParaElHookPrePush() !== null, 'el hook es bash y usa git y make: sin ellos (p. ej. dentro del contenedor) no se puede ejecutar');

it('cancela el envío a develop cuando la suite está en rojo, y por esa causa', function () {
    ['repo' => $repo, 'remoto' => $remoto, 'base' => $base] = escenarioParaElHookPrePush();
    commitNuevoParaElHookPrePush($repo);
    File::put($repo.'/resultado-suite', "1\n");

    $push = pushConElHookPrePush($repo, ['develop']);
    $salida = $push->getOutput().$push->getErrorOutput();

    expect(file_exists($repo.'/suite-invocada'))->toBeTrue("el hook no corrió la suite al publicar en develop:\n".$salida)
        ->and(str_contains($salida, 'cannot attach stdin'))->toBeFalse("docker compose exec se invocó sin -T dentro del hook:\n".$salida)
        ->and($push->isSuccessful())->toBeFalse("el hook dejó pasar un envío a develop con tests en rojo:\n".$salida)
        ->and(str_contains($salida, 'hay tests en rojo'))->toBeTrue("el envío se canceló por otra causa:\n".$salida)
        ->and(shaEnElRemotoDelHookPrePush($remoto, 'develop'))->toBe($base, 'develop se actualizó en el remoto pese a los tests en rojo');
})->skip(herramientaQueFaltaParaElHookPrePush() !== null, 'el hook es bash y usa git y make: sin ellos (p. ej. dentro del contenedor) no se puede ejecutar');

it('publica una rama de trabajo desde develop sin correr la suite', function () {
    ['repo' => $repo, 'remoto' => $remoto] = escenarioParaElHookPrePush();
    $sha = commitNuevoParaElHookPrePush($repo);

    $push = pushConElHookPrePush($repo, ['develop:refs/heads/feat/trabajo']);
    $salida = $push->getOutput().$push->getErrorOutput();

    expect($push->isSuccessful())->toBeTrue("el hook canceló el envío de una rama de trabajo:\n".$salida)
        ->and(file_exists($repo.'/suite-invocada'))->toBeFalse("el hook corrió la suite para una rama de trabajo por estar parado en develop:\n".$salida)
        ->and(shaEnElRemotoDelHookPrePush($remoto, 'feat/trabajo'))->toBe($sha, 'la rama de trabajo no llegó al remoto')
        ->and(File::get($repo.'/escaneos'))->toBe($sha."\n");
})->skip(herramientaQueFaltaParaElHookPrePush() !== null, 'el hook es bash y usa git y make: sin ellos (p. ej. dentro del contenedor) no se puede ejecutar');

it('sigue bloqueando el envío directo a main', function () {
    ['repo' => $repo, 'remoto' => $remoto] = escenarioParaElHookPrePush();
    commitNuevoParaElHookPrePush($repo);

    $push = pushConElHookPrePush($repo, ['develop:refs/heads/main']);
    $salida = $push->getOutput().$push->getErrorOutput();

    expect($push->isSuccessful())->toBeFalse("el hook dejó pasar un envío directo a main:\n".$salida)
        ->and(str_contains($salida, 'bloqueado'))->toBeTrue("el envío se canceló por otra causa:\n".$salida)
        ->and(shaEnElRemotoDelHookPrePush($remoto, 'main'))->toBeNull('main quedó creada en el remoto');
})->skip(herramientaQueFaltaParaElHookPrePush() !== null, 'el hook es bash y usa git y make: sin ellos (p. ej. dentro del contenedor) no se puede ejecutar');

it('bloquea el envío si el escáner o su pin no son válidos', function (string $defecto, string $mensaje) {
    ['repo' => $repo, 'remoto' => $remoto, 'base' => $base] = escenarioParaElHookPrePush();
    commitNuevoParaElHookPrePush($repo);

    match ($defecto) {
        'pin ausente' => File::delete($repo.'/.githooks/strix-guard.sha256'),
        'pin distinto' => File::append($repo.'/bin/strix-guard', "# contenido alterado\n"),
        'escáner ausente' => File::delete($repo.'/bin/strix-guard'),
        'escáner falla' => File::put($repo.'/resultado-escaner', "42\n"),
    };

    $push = pushConElHookPrePush($repo, ['develop:refs/heads/feat/trabajo']);
    $salida = $push->getOutput().$push->getErrorOutput();

    expect($push->isSuccessful())->toBeFalse($salida)
        ->and(str_contains($salida, $mensaje))->toBeTrue($salida)
        ->and(shaEnElRemotoDelHookPrePush($remoto, 'feat/trabajo'))->toBeNull()
        ->and(shaEnElRemotoDelHookPrePush($remoto, 'develop'))->toBe($base)
        ->and(file_exists($repo.'/suite-invocada'))->toBeFalse()
        ->and(file_exists($repo.'/escaneos'))->toBe($defecto === 'escáner falla');
})->with([
    'sin pin' => ['pin ausente', 'falta el pin'],
    'hash incorrecto' => ['pin distinto', 'no coincide con el pin'],
    'sin escáner' => ['escáner ausente', 'no esta disponible o no es ejecutable'],
    'fallo del escáner' => ['escáner falla', 'escáner sintético falló'],
]);

it('escanea todas las refs de un envío y rechaza el conjunto si falla la segunda', function (bool $rechazarSegunda) {
    ['repo' => $repo, 'remoto' => $remoto] = escenarioParaElHookPrePush();
    $primera = commitNuevoParaElHookPrePush($repo);
    $segunda = commitNuevoParaElHookPrePush($repo);
    if ($rechazarSegunda) {
        File::put($repo.'/sha-rechazado', $segunda);
    }

    $push = pushConElHookPrePush($repo, [
        $primera.':refs/heads/feat/primera',
        $segunda.':refs/heads/feat/segunda',
    ]);
    $salida = $push->getOutput().$push->getErrorOutput();

    expect($push->isSuccessful())->toBe(! $rechazarSegunda, $salida)
        ->and(File::get($repo.'/escaneos'))->toBe($primera."\n".$segunda."\n")
        ->and(file_exists($repo.'/suite-invocada'))->toBeFalse()
        ->and(shaEnElRemotoDelHookPrePush($remoto, 'feat/primera'))->toBe($rechazarSegunda ? null : $primera)
        ->and(shaEnElRemotoDelHookPrePush($remoto, 'feat/segunda'))->toBe($rechazarSegunda ? null : $segunda);
})->with([false, true]);
