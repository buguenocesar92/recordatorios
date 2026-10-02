<?php

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;

// Va para todo el archivo y no colgado de un solo test: aplicado a uno suelto,
// la base queda a medias para los que corren después —los del onboarding
// empezaron a fallar solo dentro de la suite completa, nunca en aislado—.
uses(RefreshDatabase::class);
use Spatie\Permission\Models\Role;

/**
 * Lo que trae de fábrica el diseño de un sistema nuevo.
 *
 * Cómo se reserva el espacio de una imagen es de esas cosas que, si cada
 * pantalla las decide por su cuenta, terminan distintas en cada una.
 */
it('el componente de imagen exige las dimensiones', function () {
    // Una imagen sin ancho ni alto declarados es un salto de layout esperando a
    // ocurrir: el navegador no sabe cuánto espacio reservar hasta que la baja, y
    // el contenido de abajo salta cuando llega. Por eso el componente NO tiene
    // valor por defecto para ancho y alto: quien lo use tiene que decidirlos.
    Blade::render('<x-imagen src="/foto.jpg" alt="Una foto" />');
})->throws(ViewException::class);

it('con dimensiones declaradas reserva el espacio antes de cargar', function () {
    $html = Blade::render('<x-imagen src="/foto.jpg" alt="Una foto" ancho="800" alto="600" />');

    expect($html)
        ->toContain('width="800"')
        ->toContain('height="600"')
        // La proporción también en el estilo: con ancho fluido (max-width:100%)
        // los atributos solos no reservan el alto correcto.
        ->toContain('aspect-ratio:800/600')
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"');
});

it('la imagen decorativa no obliga a inventar un texto alternativo', function () {
    // Un alt inventado para una imagen decorativa es peor que uno vacío: el
    // lector de pantalla lo lee en voz alta y estorba.
    $html = Blade::render('<x-imagen src="/adorno.svg" ancho="16" alto="16" decorativa />');

    expect($html)->toContain('alt=""')->toContain('aria-hidden="true"');
});

it('una imagen sobre el pliegue puede pedir prioridad', function () {
    // La imagen que decide el LCP no debe ir diferida: pedirla temprano es la
    // diferencia entre 1,2 y 2,8 segundos de LCP en un teléfono.
    $html = Blade::render('<x-imagen src="/portada.jpg" alt="Portada" ancho="1200" alto="600" prioritaria />');

    expect($html)->toContain('loading="eager"')
        ->toContain('fetchpriority="high"')
        ->not->toContain('loading="lazy"');
});

it('las tablas del panel recuerdan cómo las dejó cada persona', function () {
    // Filtrar, buscar y ordenar se perdía al entrar a una ficha y volver al
    // listado, que es lo que un funcionario hace cincuenta veces al día: cada
    // regreso obligaba a rehacer el filtro.
    config()->set('mfa.enabled', false);

    Role::findOrCreate('super_admin', 'web');
    $jefe = User::factory()->create();
    $jefe->assignRole('super_admin');

    $tabla = Livewire::actingAs($jefe)
        ->test(ListUsers::class)
        ->instance()
        ->getTable();

    expect($tabla->persistsFiltersInSession())->toBeTrue()
        ->and($tabla->persistsSearchInSession())->toBeTrue()
        ->and($tabla->persistsSortInSession())->toBeTrue();
})->uses(RefreshDatabase::class);
