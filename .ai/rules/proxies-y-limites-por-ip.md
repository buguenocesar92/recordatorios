---
paths:
  - 'bootstrap/app.php'
  - 'config/proxies.php'
  - 'app/Providers/AppServiceProvider.php'
  - 'routes/**'
---

# Proxies de confianza y límites por IP

## Nunca `trustProxies(at: '*')`

Con el comodín se aceptan las cabeceras `X-Forwarded-*` vengan de donde vengan, y esas
las escribe cualquiera. Medido en seguridad-graneros: los `throttle:` por IP (ingreso,
recuperar contraseña, denuncia ciudadana, token de la app de terreno) **se evaden
mandando una cabecera distinta en cada petición**, y el `ip_hash` con el que se traza
quién hizo una denuncia guarda lo que diga el atacante — trazabilidad que exige la
Ley 21.719.

El extremo contrario también rompe: sin confiar en nadie detrás del proxy,
`request->ip()` es `127.0.0.1` para todo el mundo y todos los límites caen en una sola
cubeta, bloqueándose los usuarios entre sí.

## Dónde va, y por qué ahí

`TrustProxies::at(config('proxies.confiables'))` en `AppServiceProvider::boot()`, con
`config/proxies.php` (rangos privados por omisión, acotable con `TRUSTED_PROXIES`).

**No lo muevas a `bootstrap/app.php`**: allí la configuración todavía no está cargada y
`config()` lanza excepción. Ese es el motivo de que el scaffold lo tenga en el provider
y no donde lo pondría la documentación de Laravel.

Detección en cualquier repo hermano: `grep -rn "trustProxies(at: '\*')" */bootstrap/app.php`.
