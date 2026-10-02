---
paths:
  - 'config/**'
  - '.env.example'
  - 'app/Services/**'
  - 'app/Console/Commands/**'
---

# Configuración: los defaults que mienten en silencio

## Una variable del `.env` sin su `config/*.php` detrás NO se lee

Desde Laravel 11 el framework usa sus defaults internos y **algunos están escritos a
mano, sin `env()`**. Sin error y sin aviso. Dos casos confirmados en seguridad-graneros:

- `config/app.php` ausente → `'timezone' => 'UTC'` está literal en el default del
  framework: `APP_TIMEZONE=America/Santiago` se ignora. Todo el análisis por franja
  horaria salía corrido 4 horas (un incidente de las 21:00 contaba como madrugada).
- `config/sanctum.php` ausente → `expiration => null`, que significa **nunca caduca**:
  tokens de teléfonos de terreno con acceso indefinido.

Este repo **sí publica `config/app.php`** con `timezone`, `locale`, `fallback_locale` y
`faker_locale`. No lo borres «porque Laravel ya no lo trae»: el archivo del proyecto se
**fusiona** con el del framework (`key`, `cipher` y `providers` se siguen tomando de
allí), así que no hay nada que perder. Candado: `ZonaHorariaTest`, `ConfiguracionPresenteTest`.

Verificar de verdad, no leer el `.env`:

```
php artisan tinker --execute 'echo config("app.timezone"), " ", var_export(config("sanctum.expiration"), true);'
```

Si publicas un archivo de config nuevo, fija **todas** sus claves relevantes, no solo la
que te interesa.

## `Carbon::createFromTimestamp()` sin zona devuelve UTC

Aunque `config('app.timezone')` esté bien y `now()` dé la hora correcta. Es el otro
camino al mismo desfase de 4 horas: los 18.400 marcajes de los relojes de
control-acceso quedaron 4 h adelante, todo el personal con ~240 min de atraso y el 62 %
marcado como ausente. Va siempre `createFromTimestamp($ts, config('app.timezone'))`.

Si hay que reparar un histórico, se recalcula **cada fila desde el epoch**: restar un
offset fijo rompe en cuanto el rango cruza el cambio de horario chileno (UTC-4 / UTC-3).
