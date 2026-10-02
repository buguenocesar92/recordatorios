<?php

namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;
use Spatie\Csp\Scheme;

/**
 * Lo que la política de contenido dice en TODO el sistema, sea la pantalla que sea.
 *
 * Lo que cambia entre el sitio y el panel —cómo se autorizan los guiones, y si
 * los workers y el video pueden nacer de un blob— lo declara cada preset hijo.
 *
 * Es la última línea de defensa si algún día se cuela un script donde no debe:
 * cada valor de acá es permiso para ejecutar o pintar algo. Que la lista sea
 * corta no es cosmético. Si vuelve a hacer falta un icono o una tipografía que
 * Filament no traiga self-hosted, se instala como asset propio; no se reabre la
 * política (UnaSolaPuertaDeAccesoTest y SinCdnDeTercerosTest lo vigilan).
 *
 * OJO al tocarla: una política con «frame-ancestors» DESACTIVA por completo
 * X-Frame-Options, por especificación. Acá no se incluye a propósito: manda la
 * cabecera de CabecerasDeEndurecimiento y punto.
 */
abstract class PoliticaBase implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::DEFAULT, Keyword::SELF)
            // 'unsafe-inline' en estilos: Filament y Livewire escriben atributos
            // «style» y bloques <style> sin etiquetar. Un nonce acá haría que el
            // navegador IGNORARA 'unsafe-inline' y los dejara fuera.
            ->add(Directive::STYLE, [Keyword::SELF, Keyword::UNSAFE_INLINE])
            ->add(Directive::IMG, [Keyword::SELF, Scheme::DATA, Scheme::BLOB])
            ->add(Directive::FONT, [Keyword::SELF, Scheme::DATA])
            ->add(Directive::CONNECT, Keyword::SELF)
            ->add(Directive::OBJECT, Keyword::NONE)
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::FORM_ACTION, Keyword::SELF);

        $this->guiones($policy);
    }

    /**
     * Cómo se autorizan los guiones en esta superficie, y qué más necesita.
     */
    abstract protected function guiones(Policy $policy): void;
}
