<?php

use Illuminate\Support\Facades\File;

/**
 * Candados de arranque portados desde el host del ecosistema (muni-kit-host),
 * uno por incidente vivido allá. Este archivo empieza con el que le falta al
 * propio scaffold — de donde nacen todos los demás sistemas.
 */

// ── La base de pruebas existe y tiene permisos ─────────────────────────────
// `make test` nunca funcionó en un clon limpio de este scaffold: la base
// `sistema_test` no existía ni tenía permisos, y decenas de tests morían con
// «Access denied». `make cobertura`/`make mutacion` tienen el mismo problema
// con `sistema_cobertura_test`/`sistema_mutacion_test`.
it('la base de pruebas la crea la propia infraestructura', function () {
    $script = base_path('docker/mariadb-init/01-bd-de-pruebas.sql');

    expect(File::exists($script))->toBeTrue();

    $contenido = File::get($script);

    foreach (['sistema_test', 'sistema_cobertura_test', 'sistema_mutacion_test'] as $base) {
        expect($contenido)->toContain($base);
    }

    expect(File::get(base_path('docker-compose.yml')))->toContain('docker-entrypoint-initdb.d');
});
