<?php

use Kraftdo\Shared\Rut\RutHelper;
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

it('valida y normaliza RUTs de forma instantanea (micro-optimizacion)', function () {
    $inicio = microtime(true);

    // Procesar 5000 validaciones
    for ($i = 0; $i < 5000; $i++) {
        RutHelper::validate('12.345.678-5');
        RutHelper::normalize('12345678-5');
        RutHelper::clean('12.345.678-5');
    }

    $fin = microtime(true);
    $tiempoTotal = ($fin - $inicio) * 1000; // En milisegundos
    $tiempoPromedio = $tiempoTotal / 5000;

    info("Benchmark: Validacion + normalizacion de RUT promedio = {$tiempoPromedio}ms");

    // Cada validacion/normalizacion a nivel de CPU/memoria debe tomar menos de 0.05ms
    expect($tiempoPromedio)->toBeLessThan(0.05);
});
