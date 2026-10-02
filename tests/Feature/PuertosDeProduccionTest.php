<?php

/**
 * README-despliegue.md promete «No abre puertos. El sitio queda detrás del
 * ingress del ecosistema» — pero docker-compose.prod.yml publicaba el 8000 en
 * todas las interfaces ("8000:8000", sin IP = 0.0.0.0). Sin firewall en el VPS,
 * eso es HTTP plano accesible saltándose el TLS del ingress; con
 * SESSION_SECURE_COOKIE=true el síntoma es «no puedo entrar» sin que nada
 * explique por qué.
 */
it('el override de producción no publica puertos fuera de loopback', function () {
    $compose = (string) file_get_contents(base_path('docker-compose.prod.yml'));

    expect($compose)->toContain('127.0.0.1:8000:8000');

    // "8000:8000" a secas (sin IP) publica en todas las interfaces: si vuelve a
    // aparecer sin el 127.0.0.1 delante, el candado de arriba puede pasar igual
    // porque toContain no exige que sea la ÚNICA aparición.
    expect(preg_match('/(?<!127\.0\.0\.1:)"8000:8000"/', $compose))->toBe(0,
        'Hay un "8000:8000" publicado sin acotar a 127.0.0.1.',
    );
});

it('el README de despliegue sigue prometiendo lo que el compose cumple', function () {
    $readme = (string) file_get_contents(base_path('README-despliegue.md'));

    expect($readme)->toContain('No abre puertos');
});
