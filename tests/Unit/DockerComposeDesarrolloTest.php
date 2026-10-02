<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * El compose de DESARROLLO es lo que cada proyecto generado con `./scaffold new`
 * copia tal cual: un puerto mal publicado acá nace abierto en todos los sistemas.
 *
 * Pasó el 2026-09-11: un proyecto generado desde el scaffold (consola-muni) nació
 * con la consola en 0.0.0.0:8095, alcanzable desde toda la LAN, porque el
 * servicio app publicaba "8001:8000" sin IP. El equipo no tiene cortafuegos, y
 * aunque lo tuviera no serviría: Docker inserta sus reglas por debajo de la
 * cadena INPUT que filtra ufw. El bind es lo único que protege.
 *
 * Mismo candado que web-graneros (tests/Unit/DockerComposeDesarrolloTest.php),
 * extendido a TODOS los servicios.
 */

/**
 * Cada regla publicada en el compose de desarrollo, con el servicio que la declara.
 *
 * @return list<array{servicio: string, regla: string, direccion: string}>
 */
function puertosPublicadosEnDesarrollo(): array
{
    $compose = Yaml::parseFile(dirname(__DIR__, 2).'/docker-compose.yml', Yaml::PARSE_CUSTOM_TAGS);
    $publicados = [];

    foreach ($compose['services'] as $servicio => $definicion) {
        foreach ($definicion['ports'] ?? [] as $regla) {
            if (is_array($regla)) {
                // Sintaxis larga: la dirección va en host_ip; sin ella, 0.0.0.0.
                $publicados[] = [
                    'servicio' => (string) $servicio,
                    'regla' => json_encode($regla, JSON_UNESCAPED_SLASHES) ?: '',
                    'direccion' => (string) ($regla['host_ip'] ?? ''),
                ];

                continue;
            }

            // Sintaxis corta: [DIRECCION:]PUERTO_HOST:PUERTO_CONTENEDOR. Lo que va
            // antes de los dos últimos segmentos es la dirección (puede traer `:`
            // propios, como `${BIND_HOST:-127.0.0.1}`).
            $segmentos = explode(':', (string) $regla);
            $publicados[] = [
                'servicio' => (string) $servicio,
                'regla' => (string) $regla,
                'direccion' => implode(':', array_slice($segmentos, 0, -2)),
            ];
        }
    }

    return $publicados;
}

it('ningún puerto de desarrollo se publica sin dirección ni cae por omisión fuera de loopback', function () {
    $publicados = puertosPublicadosEnDesarrollo();

    expect($publicados)->not->toBeEmpty('el compose de desarrollo dejó de publicar puertos: el candado no vigila nada');

    foreach ($publicados as ['servicio' => $servicio, 'regla' => $regla, 'direccion' => $direccion]) {
        // 1. Sin dirección, Docker publica en 0.0.0.0 (todas las interfaces). Nunca.
        expect($direccion)->not->toBe('', "{$servicio}: «{$regla}» no fija dirección: Docker publica en 0.0.0.0");

        // 2. Una variable sin valor por omisión, vacía, también publica en 0.0.0.0.
        if (str_contains($direccion, '$')) {
            expect(preg_match('/^\$\{[A-Z][A-Z0-9_]*:-([^}]+)\}$/', $direccion, $m))->toBe(1,
                "{$servicio}: «{$regla}» usa una variable sin valor por omisión: vacía, Docker publica en 0.0.0.0"
            );
            $omision = $m[1];
        } else {
            $omision = $direccion;
        }

        // 3. El valor por omisión tiene que ser loopback. Una IP concreta (la de
        //    Tailscale de UN equipo) no sirve: medido con
        //    `docker run -p 100.88.136.99:19999:80`, si la IP no existe Docker
        //    aborta con «cannot assign requested address», y el proyecto generado
        //    no levanta en otro equipo, en CI ni en un VPS.
        expect(str_starts_with($omision, '127.') || in_array($omision, ['::1', '[::1]'], true))->toBeTrue(
            "{$servicio}: «{$regla}» cae por omisión en {$omision}. Poné loopback por omisión "
            .'y la IP real (la de la tailnet) en BIND_HOST del .env de cada equipo.'
        );
    }
});

it('la app se abre a otra interfaz solo por BIND_HOST, en una sola entrada', function () {
    $delApp = array_values(array_filter(
        puertosPublicadosEnDesarrollo(),
        static fn (array $p): bool => $p['servicio'] === 'app',
    ));

    // Una sola entrada: el patrón de dos (loopback + IP de Tailscale fija) ata el
    // proyecto al equipo donde existe esa IP.
    expect($delApp)->toHaveCount(1)
        ->and($delApp[0]['direccion'])->toBe('${BIND_HOST:-127.0.0.1}');
});

it('el generador sigue sabiendo reescribir los puertos de ESTE compose', function () {
    // `./scaffold new` ya no cambia el puerto con un `sed` contra literales
    // («8001:8000», «3308:3306»): ese patrón calzaba solo mientras este
    // checkout usara esos puertos, y la variante que expuso la consola en
    // 8091/3319 generaba proyectos con LOS PUERTOS DEL SCAFFOLD sin que nada
    // avisara —«Parametrizando proyecto…» y no parametrizaba nada—, con lo que
    // dos proyectos nacidos así chocaban entre sí.
    //
    // Ahora el puerto vigente lo deduce `tools/parametrizar-puertos.php` de las
    // propias líneas «ports:». Este candado lo EJECUTA sobre una copia del
    // compose de este repo: si el formato de esas líneas cambia y la
    // herramienta deja de encontrarlas, se pone en rojo acá y no en el primer
    // proyecto generado.
    //
    // Se ejecuta en vez de leer el generador con una expresión regular porque
    // este archivo viaja al proyecto generado, donde el compose ya dice otro
    // puerto: un literal exacto pasaría en el scaffold y quedaría en rojo en
    // cada proyecto nuevo.
    $raiz = dirname(__DIR__, 2);
    $herramienta = $raiz.'/tools/parametrizar-puertos.php';

    expect(is_file($herramienta))->toBeTrue(
        'tools/parametrizar-puertos.php no está: el generador volvió a parametrizar los puertos a mano'
    );
    // Que el generador (bin/scaffold, preset `completo`) SIGA llamando a esta
    // herramienta lo vigila tests/Generador, que no viaja al proyecto: acá solo
    // se comprueba que la herramienta sabe leer el compose que sí viaja.

    $destino = sys_get_temp_dir().'/puertos-'.bin2hex(random_bytes(6));
    mkdir($destino);

    try {
        copy($raiz.'/docker-compose.yml', $destino.'/docker-compose.yml');

        $salida = [];
        $estado = 0;
        exec(sprintf('php %s %s %s %s 2>&1', escapeshellarg($herramienta), escapeshellarg($destino), '8197', '3417'), $salida, $estado);

        expect($estado)->toBe(0, 'parametrizar-puertos.php falló sobre el compose de este repo: '.implode("\n", $salida));

        $reescrito = (string) file_get_contents($destino.'/docker-compose.yml');

        // El número de puerto cambia; la dirección a la que se publica, no.
        expect(preg_match('/:8197:8000"/', $reescrito))->toBe(1, 'el puerto de la app no quedó reescrito');
        expect(preg_match('/:3417:3306"/', $reescrito))->toBe(1, 'el puerto de la base no quedó reescrito');
        expect(str_contains($reescrito, '${BIND_HOST:-127.0.0.1}:8197:8000'))->toBeTrue(
            'se perdió la dirección de bind al reescribir el puerto: el proyecto nuevo nacería publicando en 0.0.0.0'
        );
    } finally {
        @unlink($destino.'/docker-compose.yml');
        @rmdir($destino);
    }
});
