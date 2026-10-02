<?php

use Illuminate\Support\Facades\Vite;

/**
 * El nonce de la política de seguridad tiene que llegar a Vite.
 *
 * Sin esto, todo guion en línea que no ponga la vista a mano queda bloqueado.
 * El caso que lo destapó: Laravel Boost inyecta su lector de consola como guion
 * en línea, y la política lo cortaba — o sea que «browser-logs», una de las
 * herramientas que este proyecto recomienda, no devolvía nada. El único aviso
 * era un error en la consola del navegador, que es justo lo que esa herramienta
 * existe para leer.
 */
it('el nonce de la cabecera es el mismo que usa Vite', function () {
    // «/»: desde 2026-09-11 es la portada pública (grupo «sitio», no «web»),
    // servida sin sesión. El middleware corre igual y es lo que este caso mira.
    $respuesta = $this->get('/');

    $respuesta->assertOk();

    $politica = $respuesta->headers->get('Content-Security-Policy');

    expect($politica)->not->toBeNull('La respuesta no declara Content-Security-Policy');

    preg_match("/'nonce-([^']+)'/", (string) $politica, $coincidencias);

    expect($coincidencias)->toHaveCount(2, 'La política de «/» no declara ningún nonce');

    // Lo que importa: el valor que anuncia la cabecera y el que Vite reparte a
    // los guiones tienen que ser el MISMO. Si divergen, el navegador bloquea
    // todo lo que Vite etiquete, y no hay error de servidor que lo delate.
    expect(Vite::cspNonce())->toBe($coincidencias[1]);
});

// Hubo acá un segundo caso que leía el HTML de las pantallas de acceso propias y
// comprobaba que ningún guion en línea saliera sin nonce. Esas pantallas se
// retiraron al unificar el ingreso y la recuperación en el panel, y no queda
// ninguna vista HTML fuera del panel bajo la política estricta: dejarlo contra
// «/» daría verde sobre un cuerpo sin guiones, que es un test que no prueba
// nada. Un sistema que añada una pantalla pública propia trae su propio caso
// contra ESA pantalla; el mecanismo (cabecera = Vite = vista) sigue cubierto
// arriba y en NonceNoSeRegalaTest.
