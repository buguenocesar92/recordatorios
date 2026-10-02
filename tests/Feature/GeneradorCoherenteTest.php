<?php

/**
 * Lo que necesita estar en orden para que «./scaffold new» termine bien.
 *
 * El generador copia el repositorio, lo renombra y corre «composer install» y
 * «npm ci» en el proyecto nuevo. «npm ci» exige que package.json y el lock estén
 * de acuerdo: si alguien sube una versión en uno y olvida el otro, el generador
 * muere al final y quien estrena el scaffold se encuentra un proyecto a medias.
 *
 * Pasó de verdad al subir demo-engine de 1.1.1 a 1.10.0.
 */
it('el lock de npm está de acuerdo con package.json', function () {
    $paquete = json_decode((string) file_get_contents(base_path('package.json')), true);
    $lock = json_decode((string) file_get_contents(base_path('package-lock.json')), true);

    $declaradas = array_merge(
        $paquete['dependencies'] ?? [],
        $paquete['devDependencies'] ?? [],
    );

    $enElLock = $lock['packages'] ?? [];

    foreach ($declaradas as $nombre => $version) {
        $clave = 'node_modules/'.$nombre;

        // array_key_exists y no toHaveKey: las claves del lock llevan barra
        // («node_modules/motion») y toHaveKey las interpreta como una ruta
        // anidada, así que daba por ausente lo que sí estaba.
        expect(array_key_exists($clave, $enElLock))
            ->toBeTrue("«{$nombre}» está en package.json y no en el lock: «npm ci» va a fallar");

        // Las dependencias que vienen de un tarball —como demo-engine— guardan
        // en el lock la versión de su propio package.json, y es justo la que se
        // desincroniza al subir la etiqueta sin regenerar el lock.
        if (str_starts_with((string) $version, 'http')) {
            preg_match('/v?(\d+\.\d+\.\d+)/', (string) $version, $pedida);
            $instalada = $enElLock[$clave]['version'] ?? '';

            expect($instalada)->toBe(
                $pedida[1] ?? '',
                "«{$nombre}»: package.json pide {$version} y el lock tiene {$instalada}. Regenerar con «npm install --package-lock-only».",
            );
        }
    }
});
