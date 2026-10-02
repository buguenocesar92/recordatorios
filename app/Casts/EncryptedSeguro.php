<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Cifra en reposo un dato personal sensible con la clave de la aplicación.
 *
 * Se usa en vez del cast `encrypted` de Laravel por una sola diferencia: al
 * leer, una fila que no se puede descifrar —anterior a la migración, escrita
 * por fuera del modelo o cifrada con otra clave— se devuelve tal cual en vez de
 * lanzar una excepción que tumba la pantalla entera. Nunca se «adivina» el dato:
 * lo ilegible sigue siendo ilegible. Eso sí, deja un aviso en el registro (modelo
 * y campo, nunca el valor) para que una clave rotada mal no pase en silencio.
 *
 * Al escribir cifra SIEMPRE (AES-256-CBC + MAC de Laravel, IV aleatorio, así el
 * mismo dato produce textos distintos). El vacío —null o cadena vacía— pasa sin
 * cifrar, para que «no hay dato» siga siendo consultable.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class EncryptedSeguro implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            // Al registro solo el modelo y el campo, nunca el valor. Si esto
            // aparece tras rotar APP_KEY, falta APP_PREVIOUS_KEYS (README,
            // «Rotar APP_KEY»): sin ella estos datos quedan ilegibles.
            Log::warning('EncryptedSeguro: no se pudo descifrar un dato (¿APP_KEY rotada sin APP_PREVIOUS_KEYS?).', [
                'modelo' => $model::class,
                'campo' => $key,
            ]);

            return (string) $value;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return Crypt::encryptString((string) $value);
    }
}
