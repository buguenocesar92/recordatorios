<?php

declare(strict_types=1);

use App\Livewire\VerificarMfa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

/**
 * Confirmar adónde se mandó el código es útil: sin eso, quien mira la
 * pantalla no sabe si el correo llegó ni a qué casilla revisar. Pero la
 * pantalla se mira en oficinas compartidas, así que la dirección completa no
 * puede quedar a la vista: solo la versión enmascarada.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);

    Notification::fake();

    $this->usuario = User::factory()->create(['email' => 'cesar@example.com']);
    $this->actingAs($this->usuario);
});

it('la pantalla muestra el correo enmascarado', function () {
    livewire(VerificarMfa::class)
        ->assertSee('ce•••@example.com');
});

it('la pantalla no muestra la dirección de correo completa', function () {
    livewire(VerificarMfa::class)
        ->assertDontSee('cesar@example.com');
});
