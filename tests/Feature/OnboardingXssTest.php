<?php

use Kraftdo\Shared\Onboarding\OnboardingStep;
use Kraftdo\Shared\Onboarding\OnboardingTour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * El título y la descripción de un paso terminan en `innerHTML`.
 *
 * driver.js —la librería que dibuja el globo del tour— asigna los dos campos así:
 * `popover.title.innerHTML = ...` y `popover.description.innerHTML = ...`. No tiene
 * opción para desactivarlo. Es decir: lo que se escriba en esos campos se interpreta
 * como HTML en el navegador de quien vea el tour.
 *
 * La escalada es concreta, no teórica. Un rol de contenido —alguien con permiso para
 * editar tours, que es exactamente el rol que un municipio va a crear para que otro
 * escriba las guías— edita un paso del tour cuyo rol es super_admin. Ese tour arranca
 * SOLO al abrir el panel (autoStart). El script corre con la sesión del super
 * administrador: crear otra cuenta con su rol, o llevarse el listado de vecinos.
 *
 * Se escapa en el servidor y no en el cliente a propósito: es el borde por el que pasa
 * cualquier consumidor del API, y sigue protegiendo si mañana se cambia driver.js por
 * otra librería con el mismo defecto.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // Estas pruebas ejercitan el controlador, no el segundo factor: se les da la sesión
    // ya verificada para que el middleware `mfa` —que ahora cubre routes/api.php— no se
    // interponga. Que el segundo factor se exija de verdad lo cubre
    // ApiExigeSegundoFactorTest.
    $this->withSession(['auth.two_factor_verified' => true]);

    Role::create(['name' => 'operador', 'guard_name' => 'web']);

    $this->usuario = User::factory()->create();
    $this->usuario->assignRole('operador');

    $this->tour = OnboardingTour::create([
        'name' => 'Tour del panel',
        'system' => 'admin',
        'role' => 'operador',
        'surface' => 'panel',
        'active' => true,
    ]);
});

it('no devuelve HTML ejecutable en el título ni en la descripción de un paso', function () {
    OnboardingStep::create([
        'tour_id' => $this->tour->id,
        'order' => 1,
        'title' => 'Bienvenido<img src=x onerror=alert(1)>',
        'description' => '<script>fetch("/admin/usuarios")</script>',
        'position' => 'bottom',
    ]);

    $respuesta = $this->actingAs($this->usuario)
        ->getJson(route('onboarding.tour', ['system' => 'admin', 'surface' => 'panel']))
        ->assertOk();

    $paso = $respuesta->json('steps.0');

    // Ojo con la forma de escribirlo: toContain() admite varios valores y los busca
    // TODOS, así que un segundo argumento pensado como mensaje se convierte en otra
    // cosa que buscar y el test pasa en verde sin comprobar nada.
    expect(str_contains($paso['title'], '<img'))
        ->toBeFalse('el título viaja con HTML crudo hacia innerHTML');
    expect(str_contains($paso['description'], '<script'))
        ->toBeFalse('la descripción viaja con HTML crudo hacia innerHTML');
});

it('el texto legítimo sigue llegando legible y sin escapar de más', function () {
    OnboardingStep::create([
        'tour_id' => $this->tour->id,
        'order' => 1,
        'title' => 'Registro de Personas',
        'description' => 'Acá se anota al vecino y su documento.',
        'position' => 'bottom',
    ]);

    $this->actingAs($this->usuario)
        ->getJson(route('onboarding.tour', ['system' => 'admin', 'surface' => 'panel']))
        ->assertOk()
        ->assertJsonPath('steps.0.title', 'Registro de Personas')
        ->assertJsonPath('steps.0.description', 'Acá se anota al vecino y su documento.');
});
