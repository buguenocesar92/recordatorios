<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Candado: la CI de este repositorio no puede quedarse en los runners
 * hospedados de GitHub.
 *
 * La cuenta que hospeda el repo tiene los runners de GitHub bloqueados por
 * facturación («recent account payments have failed or your spending limit
 * needs to be increased»): un job con `runs-on: ubuntu-latest` no falla, se
 * queda EN COLA para siempre, y el pipeline aparece «esperando» sin decir por
 * qué. El Xeon atiende esos jobs en VMs efímeras con la etiqueta `xeon-ci`.
 *
 * La convención del ecosistema decide dónde corre cada job:
 *
 *   - con la variable de repositorio `RUNNER_CI=xeon`, el job va al Xeon;
 *   - sin la variable, sigue en GitHub (el interruptor de apagado);
 *   - una PR desde un fork SIEMPRE queda en GitHub: el runner propio no
 *     ejecuta código de terceros.
 *
 * El candado vive en la suite y no en el pipeline: un pipeline sin runners se
 * queja en días; la suite se queja en el commit.
 */

/**
 * Los workflows del repositorio, ya parseados.
 *
 * @return array<string, array<string, mixed>>
 */
$workflows = function (): array {
    $directorio = base_path('.github/workflows');

    $archivos = array_merge(
        glob($directorio.'/*.yml') ?: [],
        glob($directorio.'/*.yaml') ?: [],
    );
    sort($archivos);

    $workflows = [];

    foreach ($archivos as $archivo) {
        $parseado = Yaml::parseFile($archivo);
        $workflows[basename($archivo)] = is_array($parseado) ? $parseado : [];
    }

    return $workflows;
};

/**
 * Los jobs de todos los workflows, con el archivo y el nombre de cada uno.
 *
 * @return list<array{archivo: string, job: string, runs_on: mixed}>
 */
$jobs = function () use ($workflows): array {
    $lista = [];

    foreach ($workflows() as $archivo => $workflow) {
        foreach (($workflow['jobs'] ?? []) as $nombre => $job) {
            $lista[] = [
                'archivo' => $archivo,
                'job' => (string) $nombre,
                'runs_on' => is_array($job) ? ($job['runs-on'] ?? null) : null,
            ];
        }
    }

    return $lista;
};

/** La expresión exacta que decide entre Xeon y GitHub para cada job hospedado. */
$convencionRunnerXeon = '${{ (vars.RUNNER_CI == \'xeon\' || github.actor == \'dependabot[bot]\') && (github.event_name != \'pull_request\' || github.event.pull_request.head.repo.full_name == github.repository) && fromJSON(\'["self-hosted","xeon-ci"]\') || \'ubuntu-latest\' }}';

it('ningún job se queda clavado en un runner hospedado de etiqueta fija', function () use ($jobs) {
    $lista = $jobs();

    expect($lista)->not->toBeEmpty('No encontré jobs en .github/workflows: el candado no probaría nada.');

    foreach ($lista as $datos) {
        $runsOn = $datos['runs_on'];

        expect($runsOn)->not->toBeNull(
            "El job «{$datos['job']}» de {$datos['archivo']} no declara runs-on."
        );

        // Las etiquetas fijas no son solo un problema de facturación: una lista
        // de etiquetas de isla ([self-hosted, despliegue]) es otra cosa.
        if (! is_string($runsOn)) {
            continue;
        }

        expect(preg_match('/^(ubuntu|windows|macos)-/', $runsOn) === 1)->toBeFalse(
            "El job «{$datos['job']}» de {$datos['archivo']} sigue en el runner hospedado «{$runsOn}»: "
            .'con la facturación bloqueada ese job no arranca nunca. Usa la convención del runner del Xeon.'
        );
    }
});

it('cada job que puede ir a GitHub usa la expresión exacta del runner del Xeon', function () use ($jobs, $convencionRunnerXeon) {
    $hospedados = array_values(array_filter(
        $jobs(),
        fn (array $datos): bool => is_string($datos['runs_on']),
    ));

    expect($hospedados)->not->toBeEmpty('No encontré jobs con runs-on: el candado no probaría nada.');

    foreach ($hospedados as $datos) {
        $runsOn = $datos['runs_on'];

        expect($runsOn)->toBe(
            $convencionRunnerXeon,
            "El job «{$datos['job']}» de {$datos['archivo']} no usa la expresión exacta del runner del Xeon: "
            ."«{$runsOn}»."
        );
    }
});

it('los jobs de isla y de despliegue siguen en su runner propio', function () use ($jobs) {
    $propios = array_values(array_filter(
        $jobs(),
        fn (array $datos): bool => is_array($datos['runs_on']),
    ));

    expect($propios)->not->toBeEmpty('No encontré jobs con etiquetas propias: el candado no probaría nada.');

    foreach ($propios as $datos) {
        foreach ($datos['runs_on'] as $etiqueta) {
            expect(preg_match('/^(ubuntu|windows|macos)-/', $etiqueta) === 1)->toBeFalse(
                "El job «{$datos['job']}» de {$datos['archivo']} mezcla una etiqueta hospedada («{$etiqueta}») "
                .'con las del runner propio.'
            );
        }

        expect(in_array('self-hosted', $datos['runs_on'], true))->toBeTrue(
            "El job «{$datos['job']}» de {$datos['archivo']} no pide la etiqueta «self-hosted»."
        );
    }
});

it('el workflow que despliega solo corre código ya fusionado', function () {
    // El job que instala en el servidor corre en el runner que vive DENTRO del
    // servidor de despliegue: el más privilegiado de la flota. Este workflow no
    // se dispara con PR, así que ese runner solo ejecuta código que ya está
    // en main.
    $workflow = Yaml::parseFile(base_path('.github/workflows/desplegar.yml'));
    $disparadores = $workflow['on'] ?? [];

    expect(is_array($disparadores))->toBeTrue('No encontré los disparadores del workflow de despliegue.');

    foreach (['pull_request', 'pull_request_target'] as $evento) {
        expect(array_key_exists($evento, $disparadores))->toBeFalse(
            "El workflow de despliegue se dispara con «{$evento}»: el runner del servidor "
            .'ejecutaría el código de la PR.'
        );
    }

    expect($disparadores['push']['branches'] ?? [])->toContain('main');
});

it('ningún workflow usa eventos que corren el código del PR con el token del repositorio', function () use ($workflows) {
    // `pull_request_target` y `merge_group` corren el workflow en el contexto
    // del repositorio base, con sus secretos y su token: el vector clásico
    // para ejecutar código de una PR con permisos de escritura.
    foreach ($workflows() as $archivo => $workflow) {
        $disparadores = $workflow['on'] ?? [];
        $claves = array_keys(is_array($disparadores) ? $disparadores : []);

        foreach (['pull_request_target', 'merge_group'] as $evento) {
            expect(in_array($evento, $claves, true))->toBeFalse(
                "{$archivo} se dispara con «{$evento}»: ese evento corre el workflow con los secretos "
                .'y el token del repositorio base.'
            );
        }
    }
});

it('el token de los workflows es de solo lectura por omisión', function () use ($workflows) {
    // Declarado en el workflow, no heredado: así el runner propio —o el que
    // vive dentro del servidor de despliegue— no recibe permisos de escritura
    // por el hecho de que mañana alguien cambie el permiso por defecto del
    // repositorio.
    foreach ($workflows() as $archivo => $workflow) {
        $permisos = $workflow['permissions'] ?? null;

        expect(is_array($permisos))->toBeTrue(
            "{$archivo} no declara `permissions`: el token toma lo que tenga puesto el repositorio."
        );

        expect($permisos['contents'] ?? null)->toBe('read', "{$archivo} no declara `contents: read`.");
    }
});
