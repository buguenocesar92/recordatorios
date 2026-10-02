<?php

declare(strict_types=1);

namespace App\Support\Rutas;

/**
 * La pila con la que nace TODA ruta autenticada de `routes/api.php`.
 *
 * Vive en una constante y no escrita a mano en el archivo de rutas para que las
 * pruebas ejerzan exactamente la misma pila que usará la primera ruta real
 * (`ApiExigeSegundoFactorTest`, `RotarLaContrasenaCierraLasSesionesTest`,
 * `PantallasAutenticadasSinCacheTest`):
 *
 * - `web`: sesión, cookies cifradas, CSRF y `auth.session` (rotar la contraseña
 *   cierra la sesión).
 * - `auth`: con contraseña.
 * - `mfa`: y con el segundo factor. Con «auth» a secas, una sesión abierta con
 *   una contraseña robada que nunca pasó por la verificación llegaría acá.
 * - `sin-cache`: la respuesta lleva datos de la persona y no se guarda en el
 *   disco del navegador.
 */
final class MiddlewareDeApi
{
    /** @var list<string> */
    public const AUTENTICADA = ['web', 'auth', 'mfa', 'sin-cache'];
}
