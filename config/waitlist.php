<?php

/*
|--------------------------------------------------------------------------
| Lista de espera (fase 0)
|--------------------------------------------------------------------------
|
| `hash_salt`: sal del HMAC con que se calcula `email_hash` (búsquedas y
| límites sin exponer el correo) y `ip_hash`. Vacía en producción, el arranque
| se niega (App\Support\CredencialesDePlantilla). Se genera con
| `openssl rand -base64 32` y va en SOPS, nunca en el repositorio.
|
| `consent_version`: versión del texto de privacidad que la persona aceptó. Se
| sube cada vez que cambie el aviso (hoy es un borrador pendiente de abogado).
|
*/

return [

    'hash_salt' => (string) env('WAITLIST_HASH_SALT', ''),

    'confirm_ttl_hours' => 72,

    'resend_cooldown_hours' => 24,

    // Cuánto se conserva en failed_jobs un envío de confirmación fallido.
    'failed_job_retention_hours' => 24,

    // Por IP; en IPv6 cuenta el /64 entero (App\Waitlist\ClaveDeOrigen).
    'ip_attempts_per_minute' => 5,

    // Por dominio del correo (miles de direcciones contra una misma organización).
    'domain_attempts_per_minute' => 20,

    // Techo GLOBAL de seguridad (todas las IPs juntas), alto a propósito: solo
    // lo gastan las altas que ya pasaron los límites por IP y por dominio, así
    // que un solo origen no lo agota. Frena a quien rota miles de direcciones;
    // al tocarlo se avisa al log.
    'global_attempts_per_minute' => 300,

    // Comprobar en el trabajo de envío que el dominio recibe correo (MX, o A
    // como respaldo). Fuera de la petición: la consulta DNS no tiene tope.
    'verify_mx' => env('APP_ENV') === 'production',

    'survey_link_ttl_minutes' => 120,

    'consent_version' => '2026-10-01-borrador',

];
