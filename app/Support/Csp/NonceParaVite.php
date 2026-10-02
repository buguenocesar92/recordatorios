<?php

namespace App\Support\Csp;

use Illuminate\Support\Facades\Vite;
use Spatie\Csp\Nonce\NonceGenerator;

/**
 * Genera el nonce de la petición y se lo entrega a Vite en el mismo acto.
 *
 * spatie/laravel-csp guarda el resultado como binding «scoped» del contenedor
 * (`csp-nonce`): la cabecera y `@cspNonce` lo leen de ahí. Pero Livewire 4 no
 * conoce ese binding: escribe su <script> con `Vite::cspNonce()`
 * (FrontendAssets::nonce()), igual que `@vite`. Si el valor de Vite fuera otro
 * —o null—, el navegador bloquearía todo lo que Vite etiquete sin que ningún
 * error de servidor lo delate. Un solo valor, repartido desde un solo sitio.
 *
 * El caso que destapó la necesidad de que Vite lo tenga: Laravel Boost inyecta
 * su lector de consola como guion en línea; sin nonce la política lo bloqueaba y
 * «browser-logs» no devolvía nada, con el único aviso en la consola del
 * navegador, justo donde nadie mira cuando la herramienta que lee la consola es
 * la que está rota.
 *
 * 16 bytes aleatorios en base64: lo que pide la gramática de CSP3 (base64-value
 * de al menos 128 bits).
 */
final class NonceParaVite implements NonceGenerator
{
    public function generate(): string
    {
        $nonce = base64_encode(random_bytes(16));

        Vite::useCspNonce($nonce);

        return $nonce;
    }
}
