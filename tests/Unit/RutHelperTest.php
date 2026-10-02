<?php

use Kraftdo\Shared\Rut\RutHelper;

it('limpia el RUT dejando solo número y dígito verificador', function () {
    expect(RutHelper::clean('14.523.681-9'))->toBe('145236819');
    expect(RutHelper::clean('14523681-9'))->toBe('145236819');
    expect(RutHelper::clean('  12.345.670-k '))->toBe('12345670K');
});

it('normaliza cualquier formato a "cuerpo-dv"', function () {
    expect(RutHelper::normalize('14.523.681-9'))->toBe('14523681-9');
    expect(RutHelper::normalize('145236819'))->toBe('14523681-9');
    expect(RutHelper::normalize('12345670-k'))->toBe('12345670-K');
    expect(RutHelper::normalize('1'))->toBe('1');
});

it('formatea al estándar chileno con puntos y guión', function () {
    expect(RutHelper::format('145236819'))->toBe('14.523.681-9');
    expect(RutHelper::format('14523681-9'))->toBe('14.523.681-9');
    expect(RutHelper::format('14.523.681-9'))->toBe('14.523.681-9');
    expect(RutHelper::format('5'))->toBe('5');
});

it('valida el dígito verificador (incluyendo K)', function () {
    expect(RutHelper::validate('12.345.678-5'))->toBeTrue();
    expect(RutHelper::validate('12345678-5'))->toBeTrue();
    expect(RutHelper::validate('12.345.670-K'))->toBeTrue();
    expect(RutHelper::validate('12345670-k'))->toBeTrue();
});

it('rechaza RUT inválido, vacío o fuera de rango', function () {
    expect(RutHelper::validate('12.345.678-9'))->toBeFalse();
    expect(RutHelper::validate(''))->toBeFalse();
    expect(RutHelper::validate('hola123'))->toBeFalse();
    expect(RutHelper::validate('1-9'))->toBeFalse();
    expect(RutHelper::validate('123456789012-3'))->toBeFalse();
    expect(RutHelper::validate('12K4567-8'))->toBeFalse();
});
