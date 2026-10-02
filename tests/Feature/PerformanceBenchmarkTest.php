<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('ejecuta la resolucion de usuarios de forma ultra rapida', function () {
    // Crear un usuario de prueba
    $email = 'benchmark@example.com';
    User::factory()->create([
        'email' => $email,
        'name' => 'Benchmark User',
    ]);

    // Medir tiempo de resolucion
    $inicio = microtime(true);

    // Realizar 50 búsquedas seguidas para simular carga
    for ($i = 0; $i < 50; $i++) {
        User::where('email', $email)->first();
    }

    $fin = microtime(true);
    $tiempoTotal = ($fin - $inicio) * 1000; // En milisegundos
    $tiempoPromedio = $tiempoTotal / 50;

    info("Benchmark: Resolucion de Usuario promedio = {$tiempoPromedio}ms");

    // Debe ser menor a 15ms por consulta en base de datos local
    expect($tiempoPromedio)->toBeLessThan(15.0);
});
