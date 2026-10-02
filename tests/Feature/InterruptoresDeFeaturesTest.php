<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Pennant\Feature;

/**
 * Interruptores de features (laravel/pennant): apagar algo en producción sin
 * redeploy y sin tocar el .env.
 *
 * `config/features.php` dice con qué valor NACE cada feature; la tabla
 * `features` guarda lo que alguien encendió o apagó a mano. El scope por
 * defecto es el global: una fila por feature, no una por usuario. Con el scope
 * por usuario que Pennant trae de fábrica, «apagar para todos» solo tocaría
 * las filas ya resueltas y cada usuario nuevo nacería con el valor de
 * configuración: el interruptor no apagaría nada.
 */
uses(RefreshDatabase::class);

it('asistente-ia nace apagada', function () {
    expect(Feature::active('asistente-ia'))->toBeFalse()
        ->and(config('features.asistente-ia'))->toBeFalse();
});

it('se enciende y se apaga sin tocar la configuración', function () {
    Feature::activate('asistente-ia');

    expect(Feature::active('asistente-ia'))->toBeTrue()
        ->and(config('features.asistente-ia'))->toBeFalse();

    Feature::deactivate('asistente-ia');

    expect(Feature::active('asistente-ia'))->toBeFalse();
});

it('el interruptor es del sistema, no de quien mira: una sola fila', function () {
    Feature::activate('asistente-ia');

    $this->actingAs(User::factory()->create());

    expect(Feature::active('asistente-ia'))->toBeTrue()
        ->and(DB::table('features')->where('name', 'asistente-ia')->pluck('scope')->all())
        ->toBe(['__laravel_null']);
});

it('«por defecto» vuelve al valor de configuración VIGENTE, no al de arranque', function () {
    Feature::activate('asistente-ia');
    Feature::purge('asistente-ia');

    expect(Feature::active('asistente-ia'))->toBeFalse();

    // Si la definición leyera la configuración al arrancar en vez de al
    // resolver, este cambio no se vería hasta reiniciar.
    Feature::purge('asistente-ia');
    config()->set('features.asistente-ia', true);

    expect(Feature::active('asistente-ia'))->toBeTrue();
});

it('un worker de Octane ve el cambio en la petición siguiente, no antes', function () {
    // El gotcha: Pennant guarda en memoria lo que ya resolvió. Un proceso de
    // larga vida seguiría sirviendo el valor viejo… si Pennant no vaciara esa
    // memoria al recibir cada petición de Octane (PennantServiceProvider
    // escucha RequestReceived). Este caso comprueba las dos mitades.
    expect(Feature::active('asistente-ia'))->toBeFalse();

    // Alguien lo enciende desde OTRO proceso (el comando artisan, otro worker).
    DB::table('features')->where('name', 'asistente-ia')->update(['value' => json_encode(true)]);

    expect(Feature::active('asistente-ia'))->toBeFalse('la memoria del proceso tendría que seguir sirviendo el valor viejo: si esto es true, el gotcha desapareció y hay que revisar por qué');

    event(new RequestReceived(app(), app(), request()));

    expect(Feature::active('asistente-ia'))->toBeTrue('Pennant no vació su memoria al recibir la petición de Octane');
});

it('el comando enciende, apaga y restablece un interruptor', function () {
    $this->artisan('feature asistente-ia encendida')->assertSuccessful();
    expect(Feature::active('asistente-ia'))->toBeTrue();

    $this->artisan('feature asistente-ia apagada')->assertSuccessful();
    expect(Feature::active('asistente-ia'))->toBeFalse();

    $this->artisan('feature asistente-ia por-defecto')->assertSuccessful();
    expect(DB::table('features')->where('name', 'asistente-ia')->count())->toBe(0);
});

it('el comando rechaza una feature que no está declarada y un estado que no existe', function () {
    // Un error de tipeo no puede crear un interruptor fantasma que nadie lee.
    $this->artisan('feature asistente-la apagada')->assertFailed();
    $this->artisan('feature asistente-ia a-medias')->assertFailed();

    expect(DB::table('features')->count())->toBe(0);
});
