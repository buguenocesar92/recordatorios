<?php

use Kraftdo\Shared\Rut\RutValido;

function rutPasa(string $valor): bool
{
    $ok = true;
    (new RutValido)->validate('rut', $valor, function () use (&$ok) {
        $ok = false;
    });

    return $ok;
}

it('acepta un RUT válido en distintos formatos', function () {
    expect(rutPasa('12.345.678-5'))->toBeTrue();
    expect(rutPasa('12345678-5'))->toBeTrue();
    expect(rutPasa('123456785'))->toBeTrue();
    expect(rutPasa('  12345678-5  '))->toBeTrue();
});

it('valida correctamente el dígito verificador K', function () {
    expect(rutPasa('12.345.670-K'))->toBeTrue();
    expect(rutPasa('12345670-k'))->toBeTrue();
});

it('rechaza un RUT con dígito verificador inválido', function () {
    expect(rutPasa('12.345.678-9'))->toBeFalse();
    expect(rutPasa('12.345.678-K'))->toBeFalse();
});

it('rechaza valores vacíos, basura o fuera de rango', function () {
    expect(rutPasa(''))->toBeFalse();
    expect(rutPasa('hola123'))->toBeFalse();
    expect(rutPasa('1-9'))->toBeFalse();
    expect(rutPasa('123456789012-3'))->toBeFalse();
});

it('normaliza cualquier formato al estándar chileno', function () {
    expect(RutValido::formatear('123456785'))->toBe('12.345.678-5');
    expect(RutValido::formatear('12345678-5'))->toBe('12.345.678-5');
    expect(RutValido::formatear('12.345.678-5'))->toBe('12.345.678-5');
});
