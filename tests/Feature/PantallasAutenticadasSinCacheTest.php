<?php

use App\Http\Middleware\SinCacheParaAutenticados;
use App\Models\User;
use App\Support\Rutas\MiddlewareDeApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Las pantallas con sesión y datos no se quedan en el disco del navegador.
 *
 * La cabecera por omisión de Symfony es «no-cache, private». Suena a que no se
 * guarda y no es lo que dice: obliga a revalidar antes de reutilizar la copia,
 * pero PERMITE almacenarla, y no desactiva la caché de ida y vuelta (bfcache),
 * que restaura la página tal cual sin preguntarle nada al servidor. En un mesón
 * municipal compartido eso es que, después de cerrar sesión, «Atrás» repinta el
 * listado de vecinos. Solo «no-store» apaga las dos cosas.
 *
 * Lo que se midió al escribir esto, y es la razón de que estos casos existan: el
 * panel y «/verificar-mfa» YA salían con «no-store», pero de rebote. Lo pone
 * `Livewire\Features\SupportDisablingBackButtonCache`, un middleware global que
 * Livewire empuja y activa cuando la petición arranca un componente. Como toda
 * página de Filament lo es, el panel estaba cubierto sin que nadie lo hubiera
 * pedido. Eso deja dos cosas fuera: lo que NO arranca un componente —los
 * controladores de `routes/api.php`, que salían con «no-cache, private» a
 * secas—, y la garantía misma, que hoy depende de un detalle interno de un
 * paquete y se caería en silencio el día que Livewire cambie de opinión.
 *
 * Estos casos escriben el candado: la cabecera es una decisión de este sistema y
 * está declarada.
 *
 * Dónde NO va, que es lo delicado: ni en `CabecerasDeEndurecimiento` ni en el grupo «web»
 * entero. Ahí se llevaría por delante la caché de la portada pública y de todo
 * lo que pase por PHP sin sesión. Va exactamente donde hay sesión y datos: la
 * lista del panel de Filament, «/verificar-mfa» y el grupo de `routes/api.php`.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', true);

    $this->rolConAcceso = Role::create(['name' => 'administrator', 'guard_name' => 'web']);
    $this->rolConAcceso->givePermissionTo(
        Permission::create(['name' => 'access_panel_admin', 'guard_name' => 'web'])
    );

    $this->usuario = User::factory()->create();
    $this->usuario->assignRole($this->rolConAcceso);

    Route::middleware(MiddlewareDeApi::AUTENTICADA)
        ->get('api/sonda-cache', fn () => response()->json(['ok' => true]))
        ->name('sonda.api');
    Route::getRoutes()->refreshNameLookups();
});

it('el panel se sirve con no-store', function () {
    $respuesta = $this->actingAs($this->usuario)
        ->withSession(['auth.two_factor_verified' => true])
        ->get('/admin')
        ->assertOk();

    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store');
});

it('la pantalla del segundo factor se sirve con no-store', function () {
    $respuesta = $this->actingAs($this->usuario)
        ->get(route('mfa.verify'))
        ->assertOk();

    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store');
});

it('las rutas de api se sirven con no-store', function () {
    $respuesta = $this->actingAs($this->usuario)
        ->withSession(['auth.two_factor_verified' => true])
        ->getJson(route('sonda.api'))
        ->assertOk();

    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store');
});

it('la puerta de acceso tampoco se guarda: lleva el testigo CSRF y el correo escrito', function () {
    $respuesta = $this->get('/admin/login')->assertOk();

    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store');
});

it('no se lo pega a lo público: la portada conserva su caché', function () {
    // Si «no-store» viviera en «CabecerasDeEndurecimiento» o en el grupo «web», esta
    // respuesta —sin sesión ni datos— también lo llevaría, y con ella el resto
    // de lo público que pasa por PHP.
    //
    // La raíz es la landing pública (grupo «sitio», sin sesión ni JavaScript) y
    // se sirve con caché compartida y ETag: es la URL más visitada del sistema.
    $respuesta = $this->get('/')->assertOk();

    expect($respuesta->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('candado: no-store no vive en las cabeceras de seguridad', function () {
    // Este archivo era «SecurityHeaders» hasta que la política de contenido pasó
    // a spatie/laravel-csp; lo que quedó de él es «CabecerasDeEndurecimiento»,
    // que corre en las mismas tres pilas y sigue sin poder llevar «no-store».
    $fuente = (string) file_get_contents(app_path('Http/Middleware/CabecerasDeEndurecimiento.php'));

    expect($fuente)->not->toContain('no-store');
});

it('candado: no-store no vive en el grupo web', function () {
    $grupoWeb = app('router')->getMiddlewareGroups()['web'] ?? [];

    expect($grupoWeb)->not->toContain(SinCacheParaAutenticados::class);
});

it('candado: sobrevive a las peticiones de Livewire, que no repiten el middleware de la ruta', function () {
    // Livewire 4 no reaplica solo el middleware propio en «livewire/update»: la
    // primera carga pasa y las siguientes no. Ya pasó con el MFA en nueve
    // sistemas. Declararlo persistente es la única forma de que entre ahí.
    expect(Livewire::getPersistentMiddleware())->toContain(SinCacheParaAutenticados::class);
});
