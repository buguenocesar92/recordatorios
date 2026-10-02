<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un nonce nuevo para ESTA petición, listo ANTES de que nada se dibuje.
 *
 * Dos cosas que spatie/laravel-csp no hace solo y que acá importan:
 *
 * 1. Resolverlo antes de la vista. Spatie arma la cabecera DESPUÉS de `$next()`,
 *    y `Vite::cspNonce()` —de donde Livewire toma el suyo— es un getter: si nadie
 *    resolvió `csp-nonce` antes de renderizar, Livewire sale sin etiquetar y la
 *    política estricta del sitio lo bloquea. Resolverlo acá hace que
 *    NonceParaVite lo deje en Vite a tiempo.
 *
 * 2. Olvidar el de la petición anterior. Spatie lo guarda como binding «scoped»:
 *    Octane los vacía en cada operación, pero el proceso de tests no, y un
 *    binding que alguien convierta en singleton tampoco. El gotcha de fondo es
 *    el de web-graneros (2026-08-20): un nonce que sobrevive a su petición —en
 *    una página cacheada o en un proceso de larga vida— es una cabecera que no
 *    coincide con el cuerpo, y guiones bloqueados. NoncePorPeticionTest exige
 *    nonces distintos en dos peticiones seguidas del MISMO proceso.
 *
 * Si algún día se añade una caché de páginas, la salida NO es un marcador que se
 * sustituya en el cuerpo (regalaba un nonce válido a cualquier inyección; ver
 * NonceNoSeRegalaTest): es dejar fuera de la caché las respuestas con nonce, o
 * autorizar esos guiones por su hash, que es estable.
 */
class NoncePorPeticion
{
    public function __construct(private readonly Application $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->app->forgetInstance('csp-nonce');
        $this->app->make('csp-nonce');

        return $next($request);
    }
}
