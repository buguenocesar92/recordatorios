{{--
    Una imagen que reserva su espacio antes de existir.

    El navegador no sabe cuánto mide una imagen hasta que la descarga: sin
    dimensiones declaradas deja un hueco de cero y empuja todo lo que hay debajo
    cuando llega. Eso es el salto de layout que mide el CLS, y en un teléfono con
    datos móviles ocurre a mitad de lectura.

    Por eso «ancho» y «alto» NO tienen valor por defecto: si faltan, la vista
    falla al compilar en vez de pasar el problema al navegador de quien mire la
    página. Son las dimensiones REALES del archivo, no el tamaño en pantalla; el
    tamaño en pantalla lo decide el CSS.

    @param string $src         Dirección de la imagen.
    @param int    $ancho       Ancho real del archivo, en píxeles.
    @param int    $alto        Alto real del archivo, en píxeles.
    @param string $alt         Texto alternativo. Obligatorio salvo decorativa.
    @param bool   $decorativa  No aporta información: se oculta al lector de pantalla.
    @param bool   $prioritaria La imagen que decide el LCP: se pide temprano.
--}}
@props([
    'src',
    'ancho',
    'alto',
    'alt' => '',
    'decorativa' => false,
    'prioritaria' => false,
])

@php
    // Desde la raíz, no relativa: dentro de una ruta profunda —«/tramites/123»—
    // una ruta relativa se busca colgando de esa página y la imagen no aparece.
    $direccion = \Illuminate\Support\Str::startsWith($src, ['/', 'http', 'data:'])
        ? $src
        : '/'.ltrim($src, '/');

    // La proporción, además de los atributos: con ancho fluido —lo normal, por
    // «max-width: 100%»— los atributos solos reservan el alto del archivo y no
    // el que la imagen tendrá en pantalla. Con «aspect-ratio» el hueco se
    // encoge junto con el ancho y sigue siendo el correcto.
    $estilo = 'aspect-ratio:'.((int) $ancho).'/'.((int) $alto).';max-width:100%;height:auto';
@endphp

<img
    src="{{ $direccion }}"
    width="{{ (int) $ancho }}" height="{{ (int) $alto }}"
    alt="{{ $decorativa ? '' : $alt }}"
    @if ($decorativa) aria-hidden="true" @endif
    @if ($prioritaria)
        loading="eager"
        fetchpriority="high"
        decoding="async"
    @else
        loading="lazy"
        decoding="async"
    @endif
    {{ $attributes->merge(['style' => $estilo]) }}
>
