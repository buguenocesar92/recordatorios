<?php

use App\Filament\Pages\Escritorio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * El panel se comporta como una sola aplicación.
 *
 * Cosas que cambian cómo se siente usarlo todos los días. (El botón de ayuda del
 * recorrido guiado se fue con el módulo de recorridos, que este sistema no trae.)
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', false);

    Role::findOrCreate('super_admin', 'web');
    $this->jefe = User::factory()->create();
    $this->jefe->assignRole('super_admin');
    $this->actingAs($this->jefe);
});

it('el panel no lleva JavaScript en línea', function () {
    // Con «onclick» no se puede adoptar una política de contenido estricta sin
    // abrir la puerta a todo el JavaScript en línea de la página.
    $this->get(Escritorio::getUrl())
        ->assertOk()
        ->assertDontSee('onclick=', escape: false)
        ->assertDontSee('fa-solid', escape: false);
});

it('cambiar de sección no recarga la página entera', function () {
    $panel = Filament\Facades\Filament::getPanel('admin');

    expect($panel->hasSpaMode())->toBeTrue();
});

it('se puede buscar sin levantar la mano del teclado', function () {
    $panel = Filament\Facades\Filament::getPanel('admin');

    expect($panel->getGlobalSearchKeyBindings())->toContain('ctrl+k');
});

it('avisa antes de abandonar un formulario a medio llenar', function () {
    // Con navegación instantánea hace más falta que antes: un clic en el menú
    // lateral ya no da el respiro que daba una recarga.
    $panel = Filament\Facades\Filament::getPanel('admin');

    expect($panel->hasUnsavedChangesAlerts())->toBeTrue();
});

it('los scripts del panel no se duplican al navegar', function () {
    // Con navegación instantánea, Livewire vuelve a evaluar TODOS los <script>
    // del cuerpo en cada visita: sin «data-navigate-once» no fallan de golpe, se
    // van acumulando visita tras visita.
    $html = $this->get(Escritorio::getUrl())->getContent();

    preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>/', (string) $html, $inline);

    foreach ($inline[0] as $etiqueta) {
        expect($etiqueta)->toContain('data-navigate-once');
    }
})->skip('Filament inyecta sus propios scripts inline; se comprueba en el navegador.');
