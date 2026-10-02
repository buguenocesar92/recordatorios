<?php

use App\Livewire\VerificarMfa;
use App\Models\User;
use App\Notifications\Auth\MfaCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('le manda el código a quien está entrando', function () {
    // Con la opción de mostrarlo en pantalla APAGADA, que es como corre en
    // producción: si el código no se envía, no llega por ninguna vía.
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);

    Notification::fake();

    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)->test(VerificarMfa::class);

    Notification::assertSentTo($usuario, MfaCodeNotification::class);
});

it('el código no aparece en los registros del sistema', function () {
    // Quien pueda leer un archivo de registro entraría al panel sin más.
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);

    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)->test(VerificarMfa::class);

    $codigo = $usuario->fresh()->two_factor_code;
    $registro = storage_path('logs/laravel.log');

    if (file_exists($registro)) {
        expect((string) file_get_contents($registro))->not->toContain((string) $codigo);
    }
})->skipOnWindows();

it('sin segundo factor configurado no se envía nada', function () {
    config()->set('mfa.enabled', false);

    Notification::fake();

    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)->test(VerificarMfa::class);

    Notification::assertNothingSent();
});
