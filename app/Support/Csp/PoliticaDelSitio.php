<?php

namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;

/**
 * La política estricta: todo lo que se sirve fuera del panel.
 *
 * Sin 'unsafe-inline' ni 'unsafe-eval': con ellos la política no frena una
 * inyección, que es justo para lo que existe. Los guiones propios se autorizan
 * uno a uno con el nonce de la petición (`@cspNonce` en las vistas; `@vite` y
 * Livewire lo toman de Vite, adonde lo deja NonceParaVite). Una inyección de
 * HTML no ejecuta nada porque no lleva el nonce de ESTA petición.
 *
 * Ni worker-src ni media-src: caen a default-src 'self'. El «blob:» que FilePond
 * necesita va solo en la política del panel; no se baja acá «para que sea
 * uniforme» (.ai/rules/cabeceras-y-csp.md).
 */
final class PoliticaDelSitio extends PoliticaBase
{
    protected function guiones(Policy $policy): void
    {
        $policy
            ->add(Directive::SCRIPT, Keyword::SELF)
            ->addNonce(Directive::SCRIPT);
    }
}
