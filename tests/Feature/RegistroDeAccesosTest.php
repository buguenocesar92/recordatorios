<?php

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

/**
 * Quién entró, quién salió y quién lo intentó.
 *
 * El historial de cambios dice quién editó qué, pero no quién entró. Son
 * preguntas distintas: varios intentos fallidos seguidos contra la misma cuenta
 * es lo que hay que poder ver después de un incidente, y sin registro no se ve.
 */
uses(RefreshDatabase::class);

it('deja constancia de un ingreso', function () {
    $usuario = User::factory()->create();

    event(new Login('web', $usuario, false));

    expect(Activity::query()->where('description', 'ingreso')->count())->toBe(1);
});

it('deja constancia de un intento fallido, con la cuenta contra la que fue', function () {
    // Saber que hubo diez intentos fallidos no sirve de nada si no se sabe
    // contra qué cuenta iban.
    event(new Failed('web', null, ['email' => 'quien@ejemplo.cl', 'password' => 'lo-que-sea']));

    $registro = Activity::query()->where('description', 'ingreso fallido')->first();

    expect($registro)->not->toBeNull()
        ->and($registro->properties['cuenta'])->toBe('quien@ejemplo.cl');
});

it('deja constancia de una salida', function () {
    $usuario = User::factory()->create();

    event(new Logout('web', $usuario));

    expect(Activity::query()->where('description', 'salida')->count())->toBe(1);
});

it('no guarda la contraseña intentada', function () {
    // El evento de fallo trae las credenciales COMPLETAS, contraseña incluida.
    // Guardarlas dejaría en la base, en claro, la contraseña que alguien tecleó
    // —que suele ser la de otro sistema suyo—.
    event(new Failed('web', null, ['email' => 'quien@ejemplo.cl', 'password' => 'secreta-de-verdad']));

    $registro = Activity::query()->where('description', 'ingreso fallido')->first();

    // Exigir que el registro EXISTA es lo que hace significativa la
    // comprobación: sin esta línea, el test pasa igual cuando no se registra
    // nada en absoluto, que es justo el estado que se quiere descartar.
    expect($registro)->not->toBeNull('no se registró el intento fallido');
    expect((string) $registro->properties->toJson())->not->toContain('secreta-de-verdad');
});

it('no guarda la dirección de origen en claro', function () {
    // La Ley 21.719 trata la IP como dato personal. Para saber si dos ingresos
    // vienen del mismo lugar basta su huella, que no permite reconstruirla.
    $usuario = User::factory()->create();

    event(new Login('web', $usuario, false));

    $propiedades = (string) Activity::query()->latest('id')->first()?->properties?->toJson();

    expect($propiedades)->not->toContain('127.0.0.1')
        ->and($propiedades)->toContain('ip_hash');
});
