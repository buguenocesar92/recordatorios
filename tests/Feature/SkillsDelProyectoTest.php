<?php

/**
 * Las skills que viajan con el proyecto.
 *
 * Van DENTRO del repo, y no solo en «~/.claude/skills», para que un sistema
 * generado desde este scaffold funcione igual en otro equipo —otro municipio,
 * otra persona— donde esas skills no existen. Sin ellas, quien trabaje ahí no
 * tiene las convenciones de diseño, ni el procedimiento de auditoría, ni el
 * catálogo de pruebas de seguridad web: los improvisa, y cada uno distinto.
 *
 * El precio de que viajen es que hay dos copias de cada una, y dos copias
 * terminan divergiendo. Por eso se sincronizan en un solo sentido —desde el
 * equipo hacia el repo, con «make skills-sync»— y por eso hay una comprobación
 * que avisa cuando difieren, en vez de dejar que se separen en silencio.
 */
const SKILLS_QUE_VIAJAN = [
    'blade-livewire-design',
    'auditoria-seguridad',
    'seguridad-web',
    'web-perf',
    'mapa-funcional',
    'catalogo-funcional',
    /*
     * Estas las genera Boost contra las versiones instaladas, y las renombra
     * cuando le parece: en agosto de 2026 «pest-testing» pasó a llamarse
     * «testing-best-practices». Por eso se piden por lo que TIENEN que cubrir y
     * se aceptan sus nombres alternativos: lo que no puede faltar es la guía,
     * no una carpeta con cierto nombre.
     */
    'laravel-best-practices',
    'octane-development',
    ['testing-best-practices', 'pest-testing'],
    'tailwindcss-development',
];

it('el proyecto lleva sus skills dentro', function () {
    foreach (SKILLS_QUE_VIAJAN as $entrada) {
        $alternativas = (array) $entrada;

        $carpeta = collect($alternativas)
            ->map(fn (string $nombre): string => base_path('.claude/skills/'.$nombre))
            ->first(fn (string $ruta): bool => is_dir($ruta));

        expect($carpeta)->not->toBeNull(
            'Falta la skill «'.implode('» o «', $alternativas).
            '»: en otro equipo, quien trabaje aquí no la tendrá'
        );

        $skill = basename((string) $carpeta);

        // Hay dos formas válidas: una skill suelta con su SKILL.md, o un
        // paquete que agrupa muchas —«seguridad-web» son 67, una por tipo de
        // prueba— y entonces el SKILL.md está en cada subcarpeta.
        $suelta = file_exists($carpeta.'/SKILL.md');
        $paquete = glob($carpeta.'/*/SKILL.md') !== [];

        expect($suelta || $paquete)->toBeTrue("La skill «{$skill}» no tiene SKILL.md ni como suelta ni como paquete");
    }
});

it('la copia del repo no se separó de la del equipo', function () {
    // Esta comprobación NO puede hacerse desde la batería: corre dentro del
    // contenedor, donde «~/.claude/skills» no existe. Antes se saltaba la
    // comparación en silencio y pasaba en verde: se le metió una divergencia a
    // mano y no la vio. Un test que no puede comprobar algo tiene que decirlo,
    // no aprobarlo.
    //
    // La comprobación de verdad vive en «make skills-check», que corre en el
    // equipo y sí ve las dos copias.
    $global = getenv('HOME').'/.claude/skills/blade-livewire-design/SKILL.md';

    if (! is_file($global)) {
        expect(true)->toBeTrue();

        test()->markTestSkipped(
            'Las skills del equipo no se ven desde el contenedor. Comparar con «make skills-check».',
        );
    }

    expect(md5_file($global))->toBe(md5_file(base_path('.claude/skills/blade-livewire-design/SKILL.md')));
});
