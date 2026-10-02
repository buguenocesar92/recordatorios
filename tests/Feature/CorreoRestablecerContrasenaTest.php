<?php

use App\Models\User;
use App\Notifications\Auth\RestablecerContrasenaNotification;
use Filament\Auth\Notifications\ResetPassword as NotificacionDeFilament;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * El correo con el que se recupera una contraseña.
 *
 * Al mudar la recuperación al panel, quien pide el enlace deja de pasar por el
 * formulario propio: lo atiende Filament, que arma SU propia notificación con
 * la plantilla de fábrica de Laravel —en inglés, sin escudo ni pie municipal—
 * y NO llama al «sendPasswordResetNotification()» del modelo. Sin el reemplazo
 * en el contenedor, el mensaje más delicado que manda el sistema sería el único
 * que no se parece al resto: justo el que la gente tiene que aprender a
 * distinguir de una copia fraudulenta.
 *
 * Y la dirección tiene que venir firmada: la pantalla de restablecimiento del
 * panel exige firma, así que un enlace armado a mano —como el de la ruta propia
 * que había antes— rebota con un 403 aunque el testigo sea correcto.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate((string) config('filament-shield.super_admin.name', 'super_admin'), 'web');
});

it('el panel manda el correo del municipio, no el de fábrica', function () {
    $notificacion = app(NotificacionDeFilament::class, ['token' => 'testigo-de-prueba']);

    expect($notificacion)->toBeInstanceOf(RestablecerContrasenaNotification::class);
});

it('el enlace apunta a la pantalla del panel y viene firmado', function () {
    $usuario = User::factory()->create();

    $notificacion = app(NotificacionDeFilament::class, ['token' => 'testigo-de-prueba']);
    $correo = $notificacion->toMail($usuario);

    expect($correo->markdown)->toBe('emails.auth.restablecer-contrasena');

    $url = (string) collect($correo->viewData)->get('url');

    expect(str_contains($url, '/admin/password-reset/reset'))->toBeTrue('el enlace no lleva a la pantalla del panel');
    expect(str_contains($url, 'signature='))->toBeTrue('el enlace no viene firmado: el panel lo rechaza con 403');
    expect(str_contains($url, 'testigo-de-prueba'))->toBeTrue('el enlace no lleva el testigo');
});

it('pedir el enlace desde el panel manda el correo institucional, firmado y en el acto', function () {
    // De punta a punta, por la pantalla real del panel: el reemplazo del
    // contenedor solo sirve si es lo que Filament resuelve cuando alguien
    // aprieta el botón.
    Notification::fake();

    $usuario = User::factory()->create(['email' => 'funcionaria@graneros.test']);
    $usuario->assignRole((string) config('filament-shield.super_admin.name', 'super_admin'));

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => 'funcionaria@graneros.test'])
        ->call('request')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($usuario, RestablecerContrasenaNotification::class, function (RestablecerContrasenaNotification $n) use ($usuario): bool {
        $url = (string) collect($n->toMail($usuario)->viewData)->get('url');

        return str_contains($url, '/admin/password-reset/reset') && str_contains($url, 'signature=');
    });

    // El de fábrica no sale: sería el segundo correo, o el único si el
    // reemplazo dejara de aplicar.
    Notification::assertNotSentTo($usuario, NotificacionDeFilament::class);
});
