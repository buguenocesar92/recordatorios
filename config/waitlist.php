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
| La versión del aviso que acepta cada persona sale del texto del aviso
| (App\Waitlist\VersionDelConsentimiento), no de una cadena a mano.
|
| `purge_confirmed_after` (WAITLIST_PURGE_AFTER, fecha AAAA-MM-DD): el aviso
| promete conservar lo confirmado «hasta el lanzamiento más seis meses». Desde
| esa fecha maintenance:daily borra las inscripciones confirmadas. Obligatoria
| en producción (CredencialesDePlantilla).
|
*/

return [

    'hash_salt' => (string) env('WAITLIST_HASH_SALT', ''),

    'confirm_ttl_hours' => 72,

    // Vida máxima ABSOLUTA de una inscripción sin confirmar, contada desde
    // created_at: reinscribir el correo de otro cada 24 h ya no la alarga.
    'unconfirmed_max_lifetime_hours' => 168,

    'resend_cooldown_hours' => 24,

    // Cuánto espera un alta al candado de su correo si otra petición del mismo
    // correo va a medio camino (después: respuesta genérica sin enviar).
    'lock_wait_seconds' => 2,

    // Cuánto se conserva en failed_jobs un envío de confirmación fallido.
    'failed_job_retention_hours' => 24,

    // Por IP; en IPv6 cuenta el /64 entero (App\Waitlist\ClaveDeOrigen).
    'ip_attempts_per_minute' => 5,

    // Por dominio del correo (miles de direcciones contra una misma organización).
    'domain_attempts_per_minute' => 20,

    // Proveedores masivos EXENTOS del límite por dominio: con él, cualquiera
    // agotaba los 20/min de gmail.com y dejaba fuera a todos sus usuarios.
    // Siguen cubiertos por el límite por IP (/64), por buzón y el techo global.
    // El límite por dominio es para dominios propios usados para rociar.
    'domain_limit_exempt' => [
        'gmail.com', 'googlemail.com', 'hotmail.com', 'hotmail.es', 'outlook.com', 'outlook.es',
        'live.com', 'live.cl', 'msn.com', 'yahoo.com', 'yahoo.es', 'ymail.com', 'icloud.com',
        'me.com', 'mac.com', 'aol.com', 'proton.me', 'protonmail.com', 'gmx.com', 'zoho.com',
    ],

    // Techo GLOBAL de seguridad (todas las IPs juntas), alto a propósito: solo
    // lo gastan las altas que ya pasaron los límites por IP y por dominio, así
    // que un solo origen no lo agota. Frena a quien rota miles de direcciones;
    // al tocarlo se avisa al log.
    'global_attempts_per_minute' => 300,

    // Comprobar en el trabajo de envío que el dominio recibe correo (MX, o A
    // como respaldo). Fuera de la petición: la consulta DNS no tiene tope.
    'verify_mx' => env('APP_ENV') === 'production',

    'survey_link_ttl_minutes' => 120,

    'purge_confirmed_after' => env('WAITLIST_PURGE_AFTER') ?: null,

    // Cuánto se conserva el hash del token después de confirmar (para responder
    // 410 «ya se usó» en vez de 404 mientras el enlace sigue en el correo).
    'used_token_retention_hours' => 72,

];
