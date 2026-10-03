{{--
    Layout del sitio público. Es lo ÚNICO que hay entre la petición y el HTML:
    sin Livewire, sin Alpine, sin JavaScript. Cada cosa que se agregue acá
    viaja en cada página pública.
--}}
@props(['titulo', 'descripcion' => null, 'imagen' => null])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $titulo }} · {{ config('sitio.nombre') }}</title>
    <x-sitio.seo :titulo="$titulo" :descripcion="$descripcion" :imagen="$imagen" />
    {{ $cabeza ?? '' }}
    @vite('resources/css/sitio.css')
</head>
<body class="min-h-full bg-lienzo font-sans text-tinta antialiased">
    {{-- Primer foco de la página: quien navega con teclado salta la cabecera.
         Aparece SOBRE la barra (acento profundo en los dos modos): el anillo es
         el realce claro, no el acento, que sobre esa barra medía 1,81:1. --}}
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-10 focus:inline-flex focus:min-h-11 focus:items-center focus:rounded-md focus:bg-superficie focus:px-4 focus:py-2 focus-visible:outline-(--marca-realce-claro)">
        Saltar al contenido
    </a>
    <x-sitio.cabecera />
    <main id="contenido" tabindex="-1">
        {{ $slot }}
    </main>
    <x-sitio.pie />
</body>
</html>
