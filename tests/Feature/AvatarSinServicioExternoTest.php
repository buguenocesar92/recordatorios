<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * El avatar por defecto de Filament manda el nombre del funcionario a un tercero.
 *
 * De fábrica el panel construye la imagen con ui-avatars.com y le pasa el nombre en la
 * URL: https://ui-avatars.com/api/?name=Juan+Perez... Es una petición saliente en CADA
 * carga del panel, desde el navegador del funcionario, hacia un servicio del que la
 * Municipalidad no sabe nada: quién lo opera, dónde guarda los registros ni cuánto
 * tiempo. La nómina de quién entra al sistema y a qué hora queda en manos ajenas.
 *
 * Es dato personal tratado por un encargado que nadie contrató (Ley 21.719), y encima
 * llegaba envuelto: se descubrió porque la política de contenido lo bloqueó al empezar
 * a aplicarla al panel.
 *
 * La sustitución no pierde nada: las iniciales se dibujan localmente y se entregan como
 * data: URI, así que el navegador no sale a ningún sitio.
 */
uses(RefreshDatabase::class);

it('no construye el avatar contra un servicio externo', function () {
    $usuario = User::factory()->create(['name' => 'Juana Pérez']);

    $url = Filament::getUserAvatarUrl($usuario);

    expect(str_starts_with($url, 'data:'))
        ->toBeTrue("el avatar se pide a un tercero: {$url}");
    expect(str_contains($url, 'ui-avatars.com'))
        ->toBeFalse('el nombre del funcionario viaja a ui-avatars.com');
});

it('el avatar lleva las iniciales del usuario', function () {
    $usuario = User::factory()->create(['name' => 'Juana Pérez']);

    $url = Filament::getUserAvatarUrl($usuario);
    $svg = (string) base64_decode(Str::after($url, 'base64,'), true);

    expect(str_contains($svg, 'JP'))->toBeTrue('el avatar no muestra las iniciales');
});
