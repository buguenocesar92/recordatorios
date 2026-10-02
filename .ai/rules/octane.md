---
paths:
  - 'config/octane.php'
  - 'app/Providers/**'
  - 'app/Services/**'
  - 'app/Jobs/**'
---

# Estado de larga vida bajo Octane

El proceso no muere entre peticiones. Todo lo que dependa de eso se rompe **solo en
producción** y pasa cualquier revisión de código.

## `register_shutdown_function()` no corre por petición

`vendor/laravel/octane/bin/frankenphp-worker.php` es UN script de larga vida en bucle
sobre `frankenphp_handle_request()`: PHP ejecuta las funciones de apagado únicamente
cuando ese script termina (reciclado del worker, `MAX_REQUESTS`). La liberación «al
terminar el request» no ocurre —en control-acceso-graneros el cupo del semáforo de
cámaras solo lo soltaba el TTL de 330 s— y cada petición deja una clausura acumulada.

Usa `app()->terminating(fn () => …)`, que Octane sí ejecuta por petición. Mantén el
shutdown solo como respaldo para PHP-FPM y solo si la operación es idempotente.

## Los bindings `scoped` se descartan; los `singleton` sobreviven

`filament-shield` se registra como `scoped`. Configurarlo en `boot()` —que corre una
vez al arrancar el worker— hace que desde la **segunda** petición se reconstruya sin tu
configuración: CLI y web calculan permisos distintos, los tests (un solo ciclo de vida)
pasan y el panel devuelve 403. Va en `register()` con `$this->app->extend('filament-shield', …)`
más `alias('filament-shield', FilamentShield::class)`, importando el FQCN concreto, no
el Facade.

Al revés: `Spatie\Permission\PermissionRegistrar` es singleton y se queda vivo, así que
tocar un rol no se reflejaba hasta reiniciar. Por eso está en `config/octane.php` →
`'flush'`. **No lo saques de ahí.**

## `octane:reload` necesita `OCTANE_SERVER`

El default de `config/octane.php` es `roadrunner`, pero los contenedores arrancan con
`--server=frankenphp`. Sin la variable, el reload muere con `Undefined array key
"rpcPort"` y el síntoma engaña: la ruta existe en `route:list` y los tests pasan, pero
en vivo responde el código viejo. La variable vive en `environment:` de
`docker-compose.prod.yml` (versionado), no solo en el `.env`. Candado:
`tests/Unit/OctaneServerConfigTest.php`.
