<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * CI del PR #45: `tests/Generador/Nucleo/ComandoNewTest.php` genera un
 * proyecto real con `--frontend=inertia-react` DENTRO de la suite Pest (grupo
 * «lento», job «Pint + Pest» de `ci.yml`) para comprobar que los locks quedan
 * sincronizados. Ese preset exige npm en el PATH: sin `actions/setup-node`, el
 * job dependía de lo que ubuntu-latest trajera de fábrica, que no es la misma
 * versión que declara el Dockerfile (`FROM node:26-alpine`, la que de verdad
 * corre `npm ci` en el proyecto generado) y con la que se probó en local. El
 * job «generado» de `generador.yml` ya tenía este mismo candado
 * (`WorkflowGeneraUnProyectoTest`); este es el equivalente para `ci.yml`.
 */
function jobTestsDelCi(): array
{
    expect(is_file(base_path('.github/workflows/ci.yml')))->toBeTrue();
    $workflow = Yaml::parseFile(base_path('.github/workflows/ci.yml'));

    expect($workflow)->toBeArray()->toHaveKey('jobs');
    expect($workflow['jobs'])->toHaveKey('tests', message: 'ci.yml no tiene el job «tests» (Pint + Pest)');

    return $workflow['jobs']['tests'];
}

it('el job «tests» de ci.yml instala Node, con la versión del Dockerfile, antes de correr Pest', function () {
    $pasos = array_values(jobTestsDelCi()['steps'] ?? []);
    $indiceNode = $indicePest = null;
    foreach ($pasos as $indice => $paso) {
        if (str_starts_with((string) ($paso['uses'] ?? ''), 'actions/setup-node@')) {
            $indiceNode ??= $indice;
        }
        if (str_contains((string) ($paso['run'] ?? ''), 'vendor/bin/pest')) {
            $indicePest ??= $indice;
        }
    }

    expect($indiceNode)->not->toBeNull('el job «tests» no instala Node (actions/setup-node): ComandoNewTest.php genera con --frontend=inertia-react y sin npm en el PATH ese test no puede sincronizar los locks')
        ->and($indicePest)->not->toBeNull('el job «tests» no corre Pest')
        ->and($indiceNode)->toBeLessThan((int) $indicePest, 'actions/setup-node tiene que ir ANTES de Pest');

    expect(preg_match('/^FROM node:(\d+)/m', (string) file_get_contents(base_path('Dockerfile')), $version))->toBe(1, 'el Dockerfile ya no dice su versión de Node en «FROM node:N»');
    expect((string) ($pasos[$indiceNode]['with']['node-version'] ?? ''))->toBe($version[1], 'el job «tests» instala otra versión de Node que la del stage assets del Dockerfile');
});
