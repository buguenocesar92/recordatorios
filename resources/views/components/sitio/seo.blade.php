{{--
    Lo que un buscador y una red social leen de la página. Todo desde
    config('sitio') y app.url; nada del host de la petición (ver
    App\Support\Sitio\Direccion).

    El JSON-LD es el ÚNICO <script> de la landing. El navegador no lo ejecuta
    —es un bloque de datos— pero lleva el nonce igual: así la política de
    contenido no necesita ninguna excepción y ningún HTML inyectado puede
    hacerse pasar por él.
--}}
@props(['titulo', 'descripcion' => null, 'imagen' => null])
@php
    // FQCN y no `use`: un `use` dentro de un bloque @php compila dentro de la
    // vista y PHP lo rechaza si no está al principio del archivo compilado.
    $nombre = (string) config('sitio.nombre');
    $descripcion = (string) ($descripcion ?? config('sitio.descripcion'));
    $canonica = \App\Support\Sitio\Direccion::canonica(request());
    $tituloCompleto = $titulo.' · '.$nombre;
    $imagenAbsoluta = is_array($imagen) && ! empty($imagen['src']) ? \App\Support\Sitio\Direccion::absoluta($imagen['src']) : null;

    $entidad = (array) config('sitio.entidad');
    $datos = array_filter([
        '@context' => 'https://schema.org',
        '@type' => ($entidad['tipo'] ?? '') === 'municipalidad' ? 'GovernmentOrganization' : 'Organization',
        'name' => (string) ($entidad['nombre'] ?? $nombre),
        'url' => (string) ($entidad['url'] ?? config('app.url')),
        'telephone' => (string) ($entidad['telefono'] ?? ''),
        'email' => (string) ($entidad['correo'] ?? ''),
        'address' => ! empty($entidad['direccion'])
            ? ['@type' => 'PostalAddress', 'streetAddress' => (string) $entidad['direccion'], 'addressCountry' => 'CL']
            : '',
    ], fn ($v) => $v !== '' && $v !== null);
@endphp
<meta name="description" content="{{ $descripcion }}">
<link rel="canonical" href="{{ $canonica }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $nombre }}">
<meta property="og:title" content="{{ $tituloCompleto }}">
<meta property="og:description" content="{{ $descripcion }}">
<meta property="og:url" content="{{ $canonica }}">
<meta property="og:locale" content="es_CL">
@if ($imagenAbsoluta)
<meta property="og:image" content="{{ $imagenAbsoluta }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="preload" as="image" href="/{{ ltrim($imagen['src'], '/') }}" fetchpriority="high">
@else
<meta name="twitter:card" content="summary">
@endif
<script type="application/ld+json" @cspNonce>{!! json_encode($datos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
