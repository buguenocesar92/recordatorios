<?php

declare(strict_types=1);

namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;

/**
 * La política de la portada, que lleva el widget de Cloudflare Turnstile.
 *
 * Igual a la del sitio más exactamente dos permisos documentados por Cloudflare
 * (developers.cloudflare.com/turnstile/reference/content-security-policy):
 * cargar `api.js` y pintar su marco desde challenges.cloudflare.com. Se
 * autoriza por HOST y no por nonce a propósito: la portada se cachea con ETag
 * y un 304 reusaría un nonce viejo (.ai/rules/sitio-publico.md). Cloudflare es
 * subencargado declarado del MVP (spec §9).
 */
final class PoliticaDeLaListaDeEspera extends PoliticaBase
{
    public const ORIGEN_TURNSTILE = 'https://challenges.cloudflare.com';

    protected function guiones(Policy $policy): void
    {
        $policy
            ->add(Directive::SCRIPT, Keyword::SELF)
            ->addNonce(Directive::SCRIPT)
            ->add(Directive::SCRIPT, self::ORIGEN_TURNSTILE)
            ->add(Directive::FRAME, self::ORIGEN_TURNSTILE);
    }
}
