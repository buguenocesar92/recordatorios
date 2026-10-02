<?php

/**
 * Lo que el panel manda a bajar en cada página tiene que servir para algo.
 *
 * «AdminPanelProvider» inyecta hojas de estilo y guiones sueltos —los que no
 * pasan por Vite— con «AssetVersionado::url()». Esa función está escrita para no
 * tumbar el panel si el archivo no está: devuelve la dirección igual, sin huella.
 * Es lo correcto en producción y, a la vez, es lo que deja que un archivo
 * inexistente o vacío se quede meses sin que nadie lo note, porque no hay ningún
 * síntoma: solo una petición de más en cada carga y un 404 en la consola que
 * nadie mira.
 *
 * Ya pasó: «public/panel/js/autoguardado.js» era un archivo de una sola línea de
 * comentario —«contenido real llega en tarea posterior»— y se cargaba, con
 * «defer», en todas las páginas del panel. Este scaffold es el molde de once
 * sistemas, así que ese relleno se copiaba once veces.
 */
it('cada estático que el panel inyecta existe y trae contenido real', function () {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    preg_match_all("/AssetVersionado::url\('([^']+)'\)/", $provider, $encontrados);

    $rutas = $encontrados[1];

    expect($rutas)->not->toBeEmpty(
        'no extraje ninguna ruta de AdminPanelProvider: si cambió la forma de '
        .'inyectar los estáticos, hay que actualizar esta comprobación o se apaga sola'
    );

    foreach ($rutas as $ruta) {
        $archivo = public_path($ruta);

        // Ojo con escribirlo como toBeTrue('…') encadenado a toContain: toContain
        // admite varios valores y los busca TODOS, así que un mensaje colado como
        // segundo argumento se convierte en otra cosa que buscar y desactiva la
        // comprobación sin decir nada.
        expect(is_file($archivo))->toBeTrue(
            "El panel inyecta «{$ruta}» en cada página y ese archivo no está en public/: "
            .'AssetVersionado no falla por eso, así que el único síntoma sería un 404 silencioso.'
        );

        // Lo que queda tras quitar los comentarios y los espacios. Un archivo que
        // solo trae una leyenda no hace nada, pero cuesta una petición por página.
        $util = (string) preg_replace(
            ['#/\*.*?\*/#s', '#^\s*//.*$#m'],
            '',
            (string) file_get_contents($archivo)
        );

        expect(trim($util))->not->toBe(
            '',
            "«{$ruta}» solo tiene comentarios: el panel lo baja en cada página y no hace nada. "
            .'O se implementa, o se deja de inyectar.'
        );
    }
});
