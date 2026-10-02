<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * El código del segundo factor no puede llegar a la pantalla salvo que alguien lo pida
 * a propósito.
 *
 * Mostrarlo es una comodidad de desarrollo legítima: sin canal de envío configurado, no
 * habría forma de entrar. Pero un segundo factor que se muestra en la misma pantalla
 * donde se pide deja de ser un segundo factor: quien tenga la contraseña lo lee y pasa.
 *
 * Por eso lo que se comprueba acá no es solo que la opción exista, sino que esté
 * APAGADA por omisión y que el archivo de ejemplo —el que se copia al crear un proyecto
 * nuevo— no la encienda.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super_admin', 'web');
    config()->set('mfa.enabled', true);
});

it('por omisión el código no aparece en la pantalla', function () {
    config()->set('mfa.show_code', false);

    $usuario = User::factory()->create();
    $usuario->assignRole('super_admin');

    $this->actingAs($usuario)->get('/admin');

    $contenido = $this->actingAs($usuario)->get(route('mfa.verify'))->getContent();

    $codigo = $usuario->fresh()->two_factor_code;

    expect($codigo)->not->toBeNull('no se generó código: la comprobación no probaría nada');
    expect(str_contains($contenido, (string) $codigo))
        ->toBeFalse('el código del segundo factor viajó al navegador junto al formulario que lo pide');
});

it('encendida la opción, el código se muestra (es la comodidad de desarrollo)', function () {
    // Contraprueba: sin esto, una plantilla que nunca mostrara el código dejaría verde
    // la comprobación de arriba sin que la opción sirviera para nada.
    config()->set('mfa.show_code', true);

    $usuario = User::factory()->create();
    $usuario->assignRole('super_admin');

    $contenido = $this->actingAs($usuario)->get(route('mfa.verify'))->getContent();

    expect(str_contains($contenido, (string) $usuario->fresh()->two_factor_code))->toBeTrue();
});

it('el archivo de ejemplo no enciende la opción', function () {
    // Es el que se copia al crear un proyecto nuevo. Que el valor por omisión del
    // código sea el correcto no sirve de nada si el ejemplo lo pisa: nadie revisa
    // línea por línea un .env recién copiado.
    $ejemplo = file_get_contents(base_path('.env.example'));

    expect($ejemplo)->toMatch('/^MFA_SHOW_CODE=false$/m');
});
