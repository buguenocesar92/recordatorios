<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impide que el navegador guarde en su disco —y que restaure con «Atrás»— las
 * pantallas donde hay sesión y datos.
 *
 * Sin esto, la cabecera que sale es la que pone Symfony por omisión:
 * «no-cache, private». Suena a que no se guarda y no es lo que dice: obliga a
 * revalidar antes de reutilizar la copia, pero PERMITE almacenarla, y no
 * desactiva la caché de ida y vuelta (bfcache), que restaura la página tal cual
 * estaba sin preguntarle nada al servidor. En un mesón municipal compartido eso
 * es que, después de cerrar sesión, pulsar «Atrás» repinta el listado de vecinos
 * o la ficha que quedó abierta. Solo «no-store» apaga las dos cosas.
 *
 * **Dónde va, y por qué no en el grupo «web» ni en `CabecerasDeEndurecimiento`.** Puesto
 * ahí se lo llevaría todo por delante: la portada pública y cualquier respuesta
 * sin sesión dejarían de poder guardarse, y cada visita se volvería a bajar lo
 * mismo. Se cuelga solo donde hay sesión y datos: la lista de middleware del
 * panel de Filament —que no pasa por «web»—, «/verificar-mfa» y el grupo de
 * `routes/api.php`. La pantalla de acceso queda incluida por vivir dentro del
 * panel, y está bien: lleva el testigo CSRF y el correo que se acaba de escribir.
 *
 * **Va también en `Livewire::addPersistentMiddleware()`.** Livewire 4 no
 * reaplica por su cuenta el middleware propio en las peticiones de
 * `livewire/update`: la primera carga pasa por él y las siguientes no. Acá el
 * daño de que faltara sería menor que con un middleware que autoriza —lo que el
 * navegador guarda y restaura es el DOCUMENTO, y esas peticiones son POST con
 * JSON—, pero se declara igual: es la regla del ecosistema para todo middleware
 * propio de seguridad, y así una respuesta de Livewire no queda con una cabecera
 * distinta a la de la página que la produjo.
 *
 * **Un aviso para quien lo quite.** Hoy el panel y «/verificar-mfa» ya salían con
 * «no-store» sin que nadie lo hubiera pedido: lo pone
 * `Livewire\Features\SupportDisablingBackButtonCache`, que empuja un middleware
 * global y lo activa cuando arranca un componente. Es un detalle interno de
 * Livewire, no una garantía de este sistema: depende de que la respuesta pase
 * por un componente, así que no cubre un controlador corriente —`routes/api.php`
 * salía sin él— ni una descarga, y desaparece el día que Livewire cambie de
 * opinión. Este middleware es la garantía explícita.
 */
class SinCacheParaAutenticados
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, max-age=0, must-revalidate, private');

        // Reliquias de HTTP/1.0 que algunos proxies intermedios todavía miran.
        // No estorban y son lo único que entiende un intermediario viejo.
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
