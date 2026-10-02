<?php

/*
|--------------------------------------------------------------------------
| Vacío en el .env equivale a no configurado
|--------------------------------------------------------------------------
|
| `env('SITIO_NOMBRE')` con `SITIO_NOMBRE=` (sin comentar) en el `.env`
| devuelve `''` literal, no el segundo argumento: el default de `env()` solo
| se aplica cuando la clave NO EXISTE, nunca cuando existe vacía. Por eso el
| respaldo va con `?:`, no como segundo argumento de `env()`. `$nombre` es el
| respaldo compartido del nombre público del sitio y el de la entidad, para
| no repetir `env('APP_NAME', 'Sistema')` dos veces.
|
*/
$nombre = env('SITIO_NOMBRE') ?: env('APP_NAME', 'Sistema');

return [

    /*
    |--------------------------------------------------------------------------
    | La landing pública
    |--------------------------------------------------------------------------
    |
    | Lo que ve un buscador y cualquier visitante en «/». Se edita acá, no en
    | un CMS: la portada de un sistema cambia dos veces al año y un editor de
    | contenido sería más superficie que uso. Los textos de fábrica son
    | placeholders a propósito («Servicio 1»): dicen que nadie los escribió.
    |
    | `entidad.tipo` decide el JSON-LD (`GovernmentOrganization` para una
    | municipalidad, `Organization` para una empresa). Lo fija
    | tools/usar-paquete-compartido.php según --shared; el resto de los datos
    | de contacto van en el .env de cada despliegue.
    |
    | `ingreso` es el NOMBRE de ruta del enlace «Ingresar» de la cabecera y de la
    | llamada a la acción. Apunta al panel porque hoy es la única puerta; el
    | portal público (Fase 2) lo cambia a la suya.
    |
    */

    'nombre' => $nombre,

    'descripcion' => env('SITIO_DESCRIPCION') ?: 'Sistema para hacer trámites y consultas en línea.',

    'entidad' => [
        // ENTIDAD-DEL-SITIO: lo rellena tools/usar-paquete-compartido.php según --shared
        'tipo' => 'empresa',
        'nombre' => env('SITIO_ENTIDAD') ?: $nombre,
        'url' => env('APP_URL'),
        'direccion' => env('SITIO_DIRECCION', ''),
        'telefono' => env('SITIO_TELEFONO', ''),
        'correo' => env('SITIO_CORREO', ''),
    ],

    'ingreso' => 'filament.admin.auth.login',

    'portada' => [
        'claim' => 'Trámites y consultas en línea, sin vueltas',
        'bajada' => 'Una o dos líneas que explican para quién es este sistema y qué resuelve. Se editan en config/sitio.php.',
        'cta' => ['texto' => 'Ingresar', 'ruta' => null],
        'servicios' => [
            ['titulo' => 'Servicio 1', 'descripcion' => 'Qué se puede hacer acá y a quién le sirve.', 'ruta' => null],
            ['titulo' => 'Servicio 2', 'descripcion' => 'Qué se puede hacer acá y a quién le sirve.', 'ruta' => null],
            ['titulo' => 'Servicio 3', 'descripcion' => 'Qué se puede hacer acá y a quién le sirve.', 'ruta' => null],
        ],
        // ['src' => 'imagenes/portada.webp', 'ancho' => 1600, 'alto' => 900, 'alt' => 'Qué muestra la imagen']
        'imagen' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dominios que se dejan indexar
    |--------------------------------------------------------------------------
    |
    | Fuera de esta lista, cada respuesta lleva `X-Robots-Tag: noindex, nofollow`
    | (ver App\Support\Indexacion). Va vacía por defecto: un sistema nuevo no se
    | indexa hasta que alguien decida a propósito qué dominio sí, y esa decisión
    | se declara en el .env de cada despliegue, no acá.
    |
    | Formato en el .env: dominios separados por coma, en minúscula.
    | DOMINIOS_INDEXABLES=municipalidadgraneros.cl,www.municipalidadgraneros.cl
    |
    */

    'dominios_indexables' => array_values(array_filter(array_map(
        fn (string $dominio): string => strtolower(trim($dominio)),
        explode(',', (string) env('DOMINIOS_INDEXABLES', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Geocodificación (App\Support\Geocoder)
    |--------------------------------------------------------------------------
    |
    | Convertir una dirección en coordenadas manda ESA DIRECCIÓN a Nominatim
    | (OpenStreetMap) y a Photon (Komoot), dos servicios de terceros fuera de
    | Chile. Es un tratamiento de datos personales de la Ley 21.719 y por eso
    | va apagado de fábrica: no se enciende hasta que alguien declara acá quién
    | responde por él.
    |
    | `contacto` es un correo REAL de la institución. Cumple dos funciones a la
    | vez y ninguna es decorativa:
    |
    |  - La política de uso de Nominatim exige un User-Agent que identifique a
    |    quien consulta y permita contactarlo. El genérico que traía este
    |    scaffold («ScaffoldApp/1.0 (https://mi-municipio.cl)») no identifica a
    |    nadie, y ese es el perfil que Nominatim bloquea por IP.
    |  - Es la vía por la que alguien puede reclamar por el tratamiento.
    |
    | Sin `contacto` el Geocoder NO consulta: devuelve vacío y lo deja escrito
    | en el registro. Se prefiere quedarse sin coordenadas a mandar domicilios
    | de vecinos a un tercero sin nadie que responda por ello.
    |
    | `comuna` y `region` son el contexto que se añade a la consulta para
    | desambiguar («Avenida Central» hay en muchas partes). Estaban fijas a
    | Santiago y Región Metropolitana, lo que es sencillamente falso en las diez
    | comunas del ecosistema y empeoraba el resultado en vez de mejorarlo. Vacías,
    | no se añade nada: la búsqueda se sigue limitando a Chile.
    |
    | `lat`/`lon` son el punto que Photon usa para ordenar por cercanía. Vacíos,
    | no se envía sesgo.
    |
    | Qué se manda y qué no: solo el texto de la dirección. Nunca el nombre, el
    | RUT ni ningún otro dato de la persona a la que pertenece.
    |
    */

    'geocodificacion' => [
        'contacto' => trim((string) env('GEOCODER_CONTACTO', '')),
        'comuna' => trim((string) env('GEOCODER_COMUNA', '')),
        'region' => trim((string) env('GEOCODER_REGION', '')),
        'lat' => env('GEOCODER_LAT'),
        'lon' => env('GEOCODER_LON'),
    ],

];
