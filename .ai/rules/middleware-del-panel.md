---
paths:
  - 'app/Http/Middleware/**'
  - 'app/Providers/AppServiceProvider.php'
  - 'app/Providers/Filament/**'
---

# Middleware del panel: dónde se cuelga para que valga

## `authMiddleware` solo cubre la primera carga

Filament es una aplicación Livewire: abrir una página es una petición normal y sí pasa
por el middleware del panel, pero **borrar, editar o cambiar un estado viajan por el
punto de llamada de Livewire**, que Livewire monta con su propio grupo de middleware.

Un middleware declarado solo en `authMiddleware` deja el panel *pareciendo* protegido:
quien tenga la contraseña no ve el panel pero puede operarlo. Todo middleware propio de
seguridad va también en `Livewire::addPersistentMiddleware([...])` de
`AppServiceProvider`. Lo destapó `RedDeSeguridadTest` de muni-kit-host.

## Dos puntos que ese mecanismo NO alcanza

La subida temporal de archivos y la previsualización no llevan snapshot que verificar,
así que Livewire no les reaplica nada y quedan con `web` a secas. Se arreglan por su
cuenta y ya están puestos: `livewire.temporary_file_upload.middleware` y
`FilePreviewController::$middleware`. No los quites al tocar subidas.

## La cookie de «Recordarme» no vuelve

Duraba 14 meses contra 2 horas de sesión: al vencer la sesión reautenticaba sola, y como
la marca del segundo factor vive en la sesión perdida, el panel saltaba directo a la
pantalla del código **con el MFA como único factor** y filtrando el correo del último que
entró. Por eso `AcotarVidaDeSesion` rechaza `Auth::viaRemember()` y existe
`IgnorarCookieDeRecordar`. La casilla recuerda solo el correo (cifrado, 90 días).

Al añadir una ruta **exenta** del control de MFA (`mfa.verify` es el caso), verifica que
igual rechace la sesión recordada: si no, queda accesible a mano. Candados:
`RecordarmeNoSaltaLaContrasenaTest`, `RecordarElCorreoNoLaSesionTest`.

## `AcotarVidaDeSesion`: doce horas, y ya no mira la red

Techo de **doce** horas (2026-09-09: eran ocho, y cortaban a quien seguía trabajando a
media tarde). **Ya no se ata al prefijo de red**: la comprobación anterior (`PrefijoDeRed`,
ya borrada) cerraba la sesión de quien pasaba del wifi de la oficina a datos móviles, a
media pantalla, porque el middleware es persistente en Livewire. El riesgo aceptado queda
escrito en el docblock de la clase. No la reintroduzcas. Candado: `VidaDeSesionTest`.

## `VerificarMfa::reenviar()`: pedir otro código sin esperar

Mientras el código de MFA de diez minutos siguiera vigente, `mount()` no mandaba uno
nuevo: la única salida era esperar (o recargar, topado por `mfa-envio`). `reenviar()`
invalida el código anterior y manda uno nuevo, con tope de 3 cada 5 minutos por cuenta,
gateado por el mismo `seguridad.limitar_intentos` que el resto de los topes de esta
pantalla. Candado: `MfaReenvioDeCodigoTest`.

## No escribas nombres de panel ni de rol a mano

El panel no se llama `admin` en todos los sistemas (feria usa `feria`, discapacidad usa
`discapacidad`) y el rol de administración no siempre es `super_admin` (feria:
`administrador`). Pregunta por `Filament::getCurrentOrDefaultPanel()` y lee
`config('filament-shield.super_admin.name')`.
