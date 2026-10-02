---
paths:
  - 'app/Filament/Widgets/**'
---

# Widgets del panel

## El permiso NO se resuelve solo cuando el trait está en la clase base

`HasWidgetShield` guarda el permiso resuelto en
`protected static ?string $widgetPermissionKey`. Con el trait aplicado en la clase base
`WidgetDePanel`, esa estática es **una sola para todas las subclases**: el primer widget
evaluado la fija y desde ahí los demás piden ESE permiso. Quien tiene el permiso del
primero los ve todos; quien no, no ve ninguno aunque tenga los suyos. No da ningún error.

Por eso `WidgetDePanel` sobrescribe `getWidgetPermission()` resolviendo el permiso por
`static::class` contra `FilamentShield::getWidgets()`. **No lo borres ni lo simplifiques.**
Candado: `tests/Feature/PermisosPorWidgetTest.php` (recorre los widgets por reflexión y
exige que ninguno comparta permiso).

## `$isLazy = false` es a propósito

Filament carga los widgets en diferido con un marcador **más bajo** que el contenido
real: al llenarse, el bloque crece (~42 px con un `StatsOverviewWidget` de cuatro
tarjetas) y empuja hacia abajo toda la página. En seguridad-graneros era el CLS de
`/admin/mapa` y durante días pasó por intermitencia del runner: pasa siempre, y en
máquina rápida queda justo por debajo del umbral.

Con consultas baratas (unos `COUNT` indexados) sale más barato traerlo con la página.
Al medirlo, no mires solo `CLS < 0,1` —un umbral agregado esconde este defecto—: afirma
que la `y` del contenido en el primer fotograma pintado es la misma que al final,
midiéndola con `addInitScript`, y frena la CPU con `Emulation.setCPUThrottlingRate`.

Un widget decorativo (bienvenida) no se gatea: se **excluye** en
`config/filament-shield.php`, o genera un permiso que se togglea sin efecto.
