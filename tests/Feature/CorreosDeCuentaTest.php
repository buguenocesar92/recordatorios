<?php

use App\Models\User;
use App\Notifications\Auth\BienvenidaUsuarioNotification;
use App\Notifications\Auth\RestablecerContrasenaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Los correos que el sistema le manda a una persona por su cuenta.
 *
 * Estaban en 0 %. Son de los pocos textos que salen del sistema hacia afuera, y
 * el de restablecimiento lleva un token que abre la cuenta: si el enlace sale
 * mal armado, la persona no puede entrar; si sale de más, cualquiera puede.
 */
uses(RefreshDatabase::class);

covers(RestablecerContrasenaNotification::class, BienvenidaUsuarioNotification::class);

it('el correo de restablecimiento lleva el enlace con el token y el correo del destinatario', function () {
    $usuario = User::factory()->create(['email' => 'vecina@ejemplo.cl']);

    $correo = (new RestablecerContrasenaNotification('un-token-de-prueba'))->toMail($usuario);

    $enlace = collect($correo->viewData)->get('url');

    expect($enlace)
        ->toContain('un-token-de-prueba')
        ->and($enlace)->toContain(urlencode('vecina@ejemplo.cl'))
        // Absoluto: un enlace relativo en un correo no lleva a ninguna parte.
        ->and($enlace)->toStartWith(config('app.url'));
});

it('el correo de restablecimiento dice en cuánto caduca', function () {
    // Sin el plazo, quien abre el correo al día siguiente no entiende por qué el
    // enlace ya no sirve y vuelve a pedirlo, una y otra vez.
    config(['auth.defaults.passwords' => 'users', 'auth.passwords.users.expire' => 45]);

    $correo = (new RestablecerContrasenaNotification('t'))->toMail(User::factory()->create());

    expect(collect($correo->viewData)->get('minutos'))->toBe(45);
});

it('el asunto identifica al sistema que lo manda', function () {
    // Un vecino puede tener cuenta en varios sistemas municipales: un asunto
    // genérico no dice cuál de ellos le está escribiendo.
    config(['app.name' => 'Sistema de Prueba']);

    $correo = (new RestablecerContrasenaNotification('t'))->toMail(User::factory()->create());

    expect($correo->subject)->toContain('Sistema de Prueba');
});

it('ningún correo de cuenta lleva la contraseña ni su hash', function () {
    // El campo va oculto en el modelo, pero estos correos arman su propio
    // contenido: conviene comprobarlo sobre el resultado y no confiar en eso.
    $usuario = User::factory()->create(['email' => 'vecina@ejemplo.cl']);
    $hash = $usuario->getAuthPassword();

    $correos = [
        (new RestablecerContrasenaNotification('t'))->toMail($usuario),
        (new BienvenidaUsuarioNotification)->toMail($usuario),
    ];

    foreach ($correos as $correo) {
        $contenido = json_encode($correo->viewData).' '.$correo->subject;

        // El hash se busca en TODO, enlace incluido.
        expect($contenido)->not->toContain($hash);

        // La palabra «password» es la heurística para el texto del correo. El
        // enlace queda fuera de ESA comprobación a propósito: la pantalla del
        // panel se llama «/admin/password-reset/reset» y ahí la palabra es el
        // nombre de la ruta, no un secreto.
        $sinEnlace = json_encode(collect($correo->viewData)->except('url')->all()).' '.$correo->subject;

        expect(mb_strtolower($sinEnlace))->not->toContain('password');
    }
});

it('el correo de bienvenida saluda por el nombre y no revela de más', function () {
    $usuario = User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@ejemplo.cl']);

    $correo = (new BienvenidaUsuarioNotification)->toMail($usuario);

    expect(collect($correo->viewData)->get('nombre'))->toBe('Ana Pérez');
});
