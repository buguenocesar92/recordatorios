<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/**
 * La salida de emergencia cuando el correo no sale.
 *
 * Si el segundo factor no puede entregarse —el permiso venció, el proveedor
 * está caído, alguien se equivocó en una variable— el panel queda cerrado para
 * todos y no hay forma de abrirlo desde adentro. Las alternativas eran peores:
 * apagar el segundo factor deja a TODO el mundo entrando solo con contraseña, y
 * mostrar el código en pantalla lo muestra para cualquiera que llegue al
 * formulario.
 */
uses(RefreshDatabase::class);

it('entrega por consola el código de quien está intentando entrar', function () {
    $usuario = User::factory()->create(['email' => 'jefe@ejemplo.cl']);

    $this->artisan('panel:codigo-de-rescate', ['correo' => 'jefe@ejemplo.cl'])
        ->assertSuccessful();

    expect($usuario->fresh()->two_factor_code)->not->toBeNull();
});

it('el código que entrega es el que sirve para entrar', function () {
    // Que devuelva "un" código no basta: tiene que ser el mismo que la pantalla
    // de verificación va a aceptar, o la salida de emergencia no abre nada.
    $usuario = User::factory()->create(['email' => 'jefe@ejemplo.cl']);

    $this->artisan('panel:codigo-de-rescate', ['correo' => 'jefe@ejemplo.cl'])
        ->expectsOutputToContain((string) $usuario->fresh()->two_factor_code)
        ->assertSuccessful();
});

it('falla con una cuenta que no existe', function () {
    $this->artisan('panel:codigo-de-rescate', ['correo' => 'nadie@ejemplo.cl'])
        ->assertFailed();
});

it('no manda ningún correo: para eso justamente no sirve el correo', function () {
    Notification::fake();

    User::factory()->create(['email' => 'jefe@ejemplo.cl']);

    $this->artisan('panel:codigo-de-rescate', ['correo' => 'jefe@ejemplo.cl']);

    Notification::assertNothingSent();
});
