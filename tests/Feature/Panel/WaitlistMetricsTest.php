<?php

declare(strict_types=1);

use App\Filament\Pages\Metricas;
use App\Models\MetricDaily;
use App\Models\User;
use App\Models\WaitlistSignup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', true);
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
    config()->set('panel.vpn_cidr', []);
});

function inscritoDePrueba(string $email, array $extra = []): WaitlistSignup
{
    return WaitlistSignup::create(array_merge([
        'email' => $email,
        'interests' => [],
        'consent_version' => 'v',
        'consented_at' => now(),
        'ip_hash' => str_repeat('c', 64),
        'confirm_token_hash' => hash('sha256', $email),
        'confirm_expires_at' => now()->addDay(),
    ], $extra));
}

function duenoDelPanel(): User
{
    Role::findOrCreate('super_admin', 'web');
    $usuario = User::factory()->create();
    $usuario->assignRole('super_admin');

    return $usuario;
}

it('muestra los agregados de la lista de espera y ningún correo', function () {
    inscritoDePrueba('sin-confirmar@example.com');
    inscritoDePrueba('confirma-si@example.com', ['confirmed_at' => now()->subDay(), 'paid_penalty_last_year' => true]);
    inscritoDePrueba('confirma-no@example.com', ['confirmed_at' => now(), 'paid_penalty_last_year' => false]);
    inscritoDePrueba('confirma-nada@example.com', ['confirmed_at' => now()]);
    MetricDaily::incrementar('waitlist_unsubscribed', 2);

    $html = $this->actingAs(duenoDelPanel())
        ->withSession(['auth.two_factor_verified' => true])
        ->get(Metricas::getUrl())
        ->assertOk()
        ->assertSee('Inscritos')
        ->assertSee('Confirmados')
        ->assertSee('Bajas')
        ->assertSee('% con multa último año')
        ->assertSee('Confirmados por día')
        ->getContent();

    expect($html)->not->toContain('@example.com');

    $datos = (new Metricas)->indicadores();
    expect($datos['inscritos'])->toBe(4)
        ->and($datos['confirmados'])->toBe(3)
        ->and($datos['bajas'])->toBe(2)
        // 1 de 2 que respondieron.
        ->and($datos['porcentaje_multa'])->toBe(50);

    $serie = (new Metricas)->serieDiaria();
    expect(collect($serie)->sum('confirmados'))->toBe(3);
});

it('sin datos no divide por cero', function () {
    $datos = (new Metricas)->indicadores();

    expect($datos['inscritos'])->toBe(0)
        ->and($datos['porcentaje_multa'])->toBeNull();
});

it('sin sesión manda al ingreso del panel', function () {
    $this->get(Metricas::getUrl())->assertRedirect(route('filament.admin.auth.login'));
});

it('una cuenta sin acceso al panel no entra', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.two_factor_verified' => true])
        ->get(Metricas::getUrl())
        ->assertForbidden();
});

it('con acceso al panel pero sin el permiso de la página tampoco', function () {
    $rol = Role::findOrCreate('operador', 'web');
    $rol->givePermissionTo(Permission::findOrCreate('access_panel_admin', 'web'));
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    $this->actingAs($usuario)
        ->withSession(['auth.two_factor_verified' => true])
        ->get(Metricas::getUrl())
        ->assertForbidden();
});

it('las consultas son agregadas: la página no carga filas de personas', function () {
    $codigo = (string) file_get_contents(app_path('Filament/Pages/Metricas.php'));

    expect($codigo)->not->toContain('->get()')
        ->not->toContain('->all()')
        ->not->toContain("'email'");
});
