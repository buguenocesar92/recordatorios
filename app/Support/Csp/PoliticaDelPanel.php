<?php

namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Scheme;

/**
 * La excepción que Filament necesita: el panel y la pantalla del segundo factor.
 *
 * Filament y Alpine construyen funciones en tiempo de ejecución —así trabaja
 * Alpine— y sin 'unsafe-eval' el panel deja de responder: el botón se aprieta y
 * no pasa nada. Se acota a /admin, que además está detrás del ingreso y del
 * segundo factor; la verificación del segundo factor vive fuera de /admin pero
 * también es un componente Livewire, así que entra en la misma excepción.
 *
 * Por qué NO lleva nonce en script-src, aunque el nonce exista en la petición:
 * por especificación, cuando script-src declara un nonce el navegador IGNORA
 * 'unsafe-inline'. Filament 5.7.6 escribe cinco <script> en línea sin atributo
 * nonce (vendor/filament/filament/resources/views/components/layout/base.blade.php,
 * líneas 96, 100, 104, 148 y 156) y no ofrece ninguna forma de etiquetarlos:
 * `grep -rn nonce vendor/filament` devuelve cero archivos. Con un nonce acá,
 * esos cinco guiones dejan de correr sin un solo error de servidor —se vio el
 * 2026-08-29: cuatro bloqueos de política y tres errores de página—. Cuando
 * Filament etiquete los suyos, endurecer es quitar UNSAFE_INLINE y añadir
 * `->addNonce(Directive::SCRIPT)`, comprobando el panel en el navegador, que es
 * donde se vio el daño. Livewire ya etiqueta los suyos con el nonce de Vite.
 */
final class PoliticaDelPanel extends PoliticaBase
{
    protected function guiones(Policy $policy): void
    {
        $policy
            ->add(Directive::SCRIPT, [Keyword::SELF, Keyword::UNSAFE_EVAL, Keyword::UNSAFE_INLINE])
            // El campo de subida (FilePond) genera las miniaturas en un Web
            // Worker nacido de un blob. Sin worker-src el navegador cae a
            // default-src, lo bloquea, y las miniaturas quedan grises SIN ningún
            // error visible.
            ->add(Directive::WORKER, [Keyword::SELF, Scheme::BLOB])
            // Sin media-src el reproductor de los archivos subidos queda en
            // negro, por el mismo camino y con el mismo silencio. Engaña: parece
            // un video que tarda. Costó una tarde persiguiendo un problema de
            // rendimiento que no existía.
            ->add(Directive::MEDIA, [Keyword::SELF, Scheme::BLOB]);
    }
}
