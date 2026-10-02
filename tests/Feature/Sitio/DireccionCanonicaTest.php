<?php

use App\Support\Sitio\Direccion;
use Illuminate\Http\Request;

/**
 * El canonical y las URLs del sitemap salen de `app.url`, nunca del host con el
 * que llegó la petición. Con una caché por delante, el host del primer
 * visitante —una IP de la red interna, un dominio de pruebas— quedaría
 * cacheado para todos: es el bug real de web-graneros que motivó esto.
 */
it('la dirección canónica ignora el host de la petición', function () {
    config()->set('app.url', 'https://sistema.ejemplo.cl');

    $peticion = Request::create('http://10.0.0.5:8097/?utm=x', 'GET');

    expect(Direccion::canonica($peticion))->toBe('https://sistema.ejemplo.cl/');
});

it('conserva la ruta y descarta la consulta', function () {
    config()->set('app.url', 'https://sistema.ejemplo.cl/');

    expect(Direccion::canonica(Request::create('http://otro.test/tramites/123?p=1')))
        ->toBe('https://sistema.ejemplo.cl/tramites/123');
});

it('arma direcciones absolutas para el sitemap', function () {
    config()->set('app.url', 'https://sistema.ejemplo.cl');

    expect(Direccion::absoluta('/'))->toBe('https://sistema.ejemplo.cl/')
        ->and(Direccion::absoluta('robots.txt'))->toBe('https://sistema.ejemplo.cl/robots.txt');
});

it('la configuración del sitio trae las claves de la portada', function () {
    expect(config('sitio.nombre'))->toBeString()->not->toBe('')
        ->and(config('sitio.entidad.tipo'))->toBeIn(['organizacion', 'municipalidad', 'empresa'])
        ->and(config('sitio.ingreso'))->toBe('filament.admin.auth.login')
        ->and(config('sitio.portada.claim'))->toBeString()->not->toBe('')
        ->and(config('sitio.portada.bajada'))->toBeString()->not->toBe('')
        ->and(config('sitio.portada.imagen'))->toBeNull();
});

/**
 * `env('SITIO_NOMBRE')` con `SITIO_NOMBRE=` en el `.env` devuelve `''`, no el
 * segundo argumento por defecto: el default de `env()` solo se aplica cuando
 * la clave NO EXISTE, nunca cuando existe vacía. Un `.env.example` que
 * prometiera esas seis claves vacías (el patrón que el generador y CI copian
 * tal cual) dejaba título, `meta description`, `og:site_name` y el enlace de
 * la cabecera sin nombre accesible en todo proyecto nuevo. `config/sitio.php`
 * se protege con `?:`, que sí trata la cadena vacía como «no hay valor».
 */
it('una clave SITIO_* vacía en el .env no deja la portada sin nombre', function () {
    putenv('SITIO_NOMBRE=');

    $config = require base_path('config/sitio.php');

    expect($config['nombre'])->toBe(config('app.name'));

    putenv('SITIO_NOMBRE');
});
