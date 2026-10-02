<?php

use Muni\Candados\Candados;
use Muni\Shared\Errores\ReporteDeErrores;

/**
 * Los candados del ecosistema municipal, heredados de
 * `muni-graneros/laravel-muni-candados` en vez de copiados a mano.
 *
 * No se llama a `Candados::todos()` a secas: ese método no acepta argumentos
 * (llama a cada candado con SUS valores por omisión, hardcodeados adentro), y
 * `App\Support\ReporteDeErrores` ya no existe —se adoptó
 * `Muni\Shared\Errores\ReporteDeErrores` (laravel-muni-shared ^1.20)—. Sin
 * decirle la clase nueva, `erroresNoSalenDelPais()` seguiría buscando la
 * clase borrada. El resto de los candados sí usa sus valores por omisión, en
 * el mismo orden que `Candados::todos()`.
 *
 * Los demás valores por omisión calzan uno a uno con los de este sistema:
 * mismo directorio de seeders, mismo Dockerfile en la raíz con
 * `dunglas/frankenphp`, misma clave de configuración `proxies.confiables`,
 * mismos directorios `app/` y `routes/`, y la misma migración
 * `2026_09_02_120000_olvidar_los_testigos_de_recordarme`.
 */
Candados::seedersSinCredencialesEnProduccion();
Candados::imagenDeProduccion();
Candados::erroresNoSalenDelPais(clase: ReporteDeErrores::class);
Candados::proxiesDeConfianza();
Candados::nadieEmiteCookieDeRecordar();
Candados::cookieDeRecordarInerte();
Candados::guardaDeCredencialesDePlantilla();

/**
 * Este scaffold ya no reparte `public/sw.js` ni `public/manifest.webmanifest`
 * (se quitó la «PWA fantasma» en el commit 7f7e2db): con ningún archivo que
 * calce el patrón, el candado pasa vacío. Se registra igual —es la plantilla
 * de la que nacen los sistemas nuevos— para que un `sw*.js` o
 * `manifest*.webmanifest` que se agregue sin registrar quede atrapado desde
 * el primer commit, en vez de repetir la fantasma en el próximo
 * `./scaffold new`.
 */
Candados::pwaSinRestosDelScaffold();

/**
 * `higieneDeLaEtapaDeAssets` (0.3.0) NO está en `todos()` todavía: el día que
 * se promovió al paquete solo lo cumplía `rrhh-graneros`, y meterlo en
 * `todos()` habría puesto en rojo a los demás sistemas de golpe. Se registra
 * a mano acá porque este scaffold ES uno de los tres con `demo-engine`
 * (arrastra `ffmpeg-static`, cuyo `postinstall` se baja ~70 MB desde GitHub)
 * alcanzable en el build de producción — y porque este repo es la plantilla
 * de la que nacen los sistemas nuevos: lo que se corrige acá no se vuelve a
 * repetir en el próximo `./scaffold new`.
 *
 * Los valores por omisión calzan con este sistema: mismo `Dockerfile` y
 * `package.json`/`package-lock.json` en la raíz, mismos entrypoints en
 * `resources/js/*.js`.
 */
Candados::higieneDeLaEtapaDeAssets();
