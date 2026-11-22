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

/**
 * ¿La expresión es la convención del runner del Xeon?
 *
 * Se comprueban las PIEZAS y no la cadena entera: la excepción de dependabot
 * es opcional (depende de si al código de dependencias sin revisar le conviene
 * o no pisar el runner propio) y el candado tiene que seguir sirviendo con o
 * sin ella.
 */
$esLaConvencion = function (string $runsOn): bool {
    return str_contains($runsOn, "vars.RUNNER_CI == 'xeon'")
        && str_contains($runsOn, 'fromJSON(\'["self-hosted","xeon-ci"]\')')
        && str_contains($runsOn, "github.event_name != 'pull_request'")
        && str_contains($runsOn, 'github.event.pull_request.head.repo.full_name == github.repository')
        && preg_match("/\\|\\| '(?<respaldo>ubuntu|windows|macos)-[a-z0-9._-]*' \\}\\}$/", $runsOn) === 1;
};

/** La etiqueta de GitHub a la que vuelve el job si el interruptor está apagado. */
$respaldo = function (string $runsOn): ?string {
    return preg_match("/\\|\\| '(?<respaldo>[a-z0-9._-]+)' \\}\\}$/", $runsOn, $coincidencias) === 1
        ? $coincidencias['respaldo']
        : null;
};

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

it('cada job que puede ir a GitHub usa la convención del runner del Xeon', function () use ($jobs, $esLaConvencion, $respaldo) {
    $hospedados = array_values(array_filter(
        $jobs(),
        fn (array $datos): bool => is_string($datos['runs_on']),
    ));

    expect($hospedados)->not->toBeEmpty('No encontré jobs con runs-on: el candado no probaría nada.');

    foreach ($hospedados as $datos) {
        $runsOn = $datos['runs_on'];

        expect($esLaConvencion($runsOn))->toBeTrue(
            "El job «{$datos['job']}» de {$datos['archivo']} no usa la convención del runner del Xeon: "
            ."«{$runsOn}». Tiene que ir con la etiqueta «self-hosted,xeon-ci» cuando RUNNER_CI=xeon, "
            .'volver a GitHub sin la variable, y quedarse en GitHub en una PR desde un fork.'
        );

        // El respaldo conserva la etiqueta que el job tenía: aquí todos eran
        // `ubuntu-latest`, y volver a GitHub no es una opción —la facturación
        // está bloqueada—, pero el interruptor sigue siendo el que manda.
        expect($respaldo($runsOn))->toBe('ubuntu-latest');
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

it('ningún job se salta el interruptor de apagado del runner propio', function () use ($jobs) {
    // RUNNER_CI es lo único que decide si este repo usa el Xeon o los runners
    // de GitHub. Una disyunción como `|| github.actor == 'dependabot[bot]'`
    // rompe ese interruptor: con la variable borrada, un Dependabot seguía
    // yendo al self-hosted, llevando código de dependencias sin revisar a la
    // VM propia. Es el mismo arreglo que se hizo en el resto del ecosistema.
    foreach ($jobs() as $datos) {
        $runsOn = $datos['runs_on'];

        if (! is_string($runsOn)) {
            continue;
        }

        expect(str_contains($runsOn, 'dependabot'))->toBeFalse(
            "El job «{$datos['job']}» de {$datos['archivo']} manda al runner propio sin mirar RUNNER_CI: "
            ."«{$runsOn}»."
        );
    }
});
