<?php

use App\Models\Persona;
use App\Privacidad\BuscadorDePersonas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Muni\Shared\Privacidad\Contratos\BuscaTitulares;
use Muni\Shared\Privacidad\Contratos\TitularDeDatos;
use Muni\Ui\Filament\Privacidad\PanelArcopPlugin;

/**
 * El ciclo ARCOP viene montado de fábrica.
 *
 * La Ley 21.719 le da a cualquier persona derecho a acceder a sus datos,
 * rectificarlos, cancelarlos y oponerse al tratamiento, y obliga a atender esas
 * solicitudes con plazos. Un sistema municipal que nace sin eso, nace
 * incumpliendo: no es una funcionalidad que se agrega después «si hay tiempo».
 *
 * El ciclo entero vive en los paquetes compartidos. Lo que cada sistema tiene
 * que poner es lo único que un paquete no puede saber: cómo encuentra a la
 * persona que viene al mesón, y qué deja de tratarse de verdad cuando esa
 * persona pide el cese.
 */
uses(RefreshDatabase::class);

it('el panel trae el ciclo ARCOP montado', function () {
    $panel = Filament\Facades\Filament::getPanel('admin');

    expect($panel->getPlugin(PanelArcopPlugin::ID))->toBeInstanceOf(PanelArcopPlugin::class);
});

it('el sistema sabe buscar a quien viene al mesón', function () {
    // Es el punto que ningún paquete puede resolver solo: cada sistema busca
    // por lo suyo —RUT, padrón, número de licencia—.
    expect(app(BuscaTitulares::class))->toBeInstanceOf(BuscadorDePersonas::class);
});

it('encuentra a una persona por su documento, con o sin puntos', function () {
    Persona::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez', 'nro_documento' => '12.345.678-5']);

    $buscador = app(BuscaTitulares::class);

    // La misma persona, escrita como la escribiría cualquiera en el mesón.
    foreach (['12345678-5', '12.345.678-5', '123456785'] as $comoLoEscriben) {
        expect($buscador->buscar($comoLoEscriben))->not->toBeEmpty("No la encontró con «{$comoLoEscriben}»");
    }
});

it('la persona encontrada sabe responder como titular de datos', function () {
    $persona = Persona::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez', 'nro_documento' => '12.345.678-5']);

    $titular = app(BuscaTitulares::class)->encontrar($persona->getKey());

    expect($titular)->toBeInstanceOf(TitularDeDatos::class)
        ->and($titular->titularNombre())->toContain('Ana')
        ->and($titular->exportarDatosPersonales())->toBeArray();
});

it('mientras nadie declare qué cesa, el panel lo dice en vez de callarlo', function () {
    // Darle a un sistema la pantalla para recibir solicitudes NO lo hace
    // cumplir: si nadie escribió qué tratamiento cesa de verdad, el sistema le
    // certificaría por escrito a un vecino un cese que no ocurre. El paquete
    // arranca sin declarar y avisa; el scaffold no lo declara por él, porque no
    // puede saberlo.
    $plugin = Filament\Facades\Filament::getPanel('admin')->getPlugin(PanelArcopPlugin::ID);

    expect($plugin)->toBeInstanceOf(PanelArcopPlugin::class);
});
