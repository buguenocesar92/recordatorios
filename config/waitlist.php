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

    'ip_attempts_per_minute' => 5,

    'survey_link_ttl_minutes' => 120,

    'consent_version' => '2026-10-01-borrador',

];
