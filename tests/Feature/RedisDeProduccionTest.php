<?php

use Symfony\Component\Yaml\Yaml;

/**
 * El .env de producción de un sistema generado (lo materializa
 * secretos-graneros/bin/nuevo-proyecto.sh) trae un REDIS_PASSWORD aleatorio y
 * Laravel hace AUTH con él. Un Redis sin `requirepass` responde «ERR AUTH
 * <password> called without any password configured» y `migrate` muere en el
 * primer despliegue. El compose base sigue sin clave (desarrollo y pruebas).
 */
it('el redis de producción exige la clave del .env y su healthcheck se autentica', function () {
    $compose = Yaml::parseFile(base_path('docker-compose.prod.yml'), Yaml::PARSE_CUSTOM_TAGS);
    $redis = $compose['services']['redis'] ?? null;

    expect($redis)->toBeArray('falta el servicio redis en docker-compose.prod.yml');

    $comando = implode(' ', (array) ($redis['command'] ?? []));

    // La clave no se interpola en el command (quedaría en `docker inspect` y en
    // `ps`): la lee el shell del contenedor de REDISCLI_AUTH, que redis-cli
    // también usa en el healthcheck.
    expect(str_contains($comando, '--requirepass'))->toBeTrue('redis arranca sin requirepass')
        ->and(str_contains($comando, '${REDIS_PASSWORD'))->toBeFalse('la clave quedaría en docker inspect')
        ->and(str_contains($comando, '$$REDISCLI_AUTH'))->toBeTrue();

    expect($redis['environment'] ?? [])->toContain('REDISCLI_AUTH=${REDIS_PASSWORD:?REDIS_PASSWORD sin definir en .env}');

    $salud = implode(' ', (array) ($redis['healthcheck']['test'] ?? []));
    expect(str_contains($salud, 'redis-cli'))->toBeTrue()
        ->and(str_contains($salud, 'ping'))->toBeTrue();
});

it('el compose base deja redis sin clave para desarrollo y pruebas', function () {
    $base = (string) file_get_contents(base_path('docker-compose.yml'));

    expect(str_contains($base, 'requirepass'))->toBeFalse();
});
