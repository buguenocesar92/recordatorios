<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function desdeIp(string $ip): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->get('/admin/login');
}

it('con la subred de la VPN configurada, solo esa subred ve el panel', function () {
    config()->set('panel.vpn_cidr', ['10.8.0.0/24']);

    desdeIp('10.8.0.7')->assertOk();
    desdeIp('203.0.113.5')->assertNotFound();
});

it('acepta varias subredes', function () {
    config()->set('panel.vpn_cidr', ['10.8.0.0/24', 'fd00::/8']);

    desdeIp('fd00::1')->assertOk();
    desdeIp('10.9.0.1')->assertNotFound();
});

it('en producción sin subred configurada el panel queda cerrado (falla cerrado)', function () {
    config()->set('panel.vpn_cidr', []);
    $this->app->detectEnvironment(fn (): string => 'production');

    desdeIp('10.8.0.7')->assertNotFound();
});

it('fuera de producción sin subred no restringe (desarrollo)', function () {
    config()->set('panel.vpn_cidr', []);

    desdeIp('203.0.113.5')->assertOk();
});

it('la pantalla del segundo factor también queda detrás de la VPN', function () {
    config()->set('panel.vpn_cidr', ['10.8.0.0/24']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->get('/verificar-mfa')->assertNotFound();
});

it('la portada pública no se ve afectada', function () {
    config()->set('panel.vpn_cidr', ['10.8.0.0/24']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->get('/')->assertOk();
});

it('la variable se lee de ADMIN_VPN_CIDR separada por comas', function () {
    expect((string) file_get_contents(config_path('panel.php')))->toContain("env('ADMIN_VPN_CIDR'");
});
