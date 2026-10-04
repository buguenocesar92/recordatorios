<?php

declare(strict_types=1);

use App\Http\Middleware\SoloDesdeLaVpn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportFileUploads\FilePreviewController;

/**
 * La subida temporal de Livewire y su previsualización no pasan por el
 * middleware persistente (no llevan snapshot), así que el candado de la VPN no
 * les llegaba: con una sesión robada se podía subir desde cualquier parte.
 */
uses(RefreshDatabase::class);

it('la subida temporal y la previsualización llevan el candado de la VPN', function () {
    expect(config('livewire.temporary_file_upload.middleware'))->toContain(SoloDesdeLaVpn::class)
        ->and(FilePreviewController::$middleware)->toContain(SoloDesdeLaVpn::class);
});

it('fuera de la VPN, la subida responde 404 aunque haya sesión', function () {
    config()->set('panel.vpn_cidr', ['10.8.0.0/24']);
    $usuario = User::create(['name' => 'Dueño', 'email' => 'duenio@example.com', 'password' => bcrypt('una-clave-larga-123')]);

    $this->actingAs($usuario)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post(route('livewire.upload-file'))
        ->assertNotFound();
});

it('fuera de la VPN, la previsualización responde 404 aunque haya sesión', function () {
    config()->set('panel.vpn_cidr', ['10.8.0.0/24']);
    $usuario = User::create(['name' => 'Dueño', 'email' => 'duenio@example.com', 'password' => bcrypt('una-clave-larga-123')]);

    $this->actingAs($usuario)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->get(route('livewire.preview-file', ['filename' => 'x.png']))
        ->assertNotFound();
});
