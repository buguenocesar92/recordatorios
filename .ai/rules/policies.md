---
paths:
  - 'app/Policies/**'
  - 'app/Models/**'
---

# Policies y reglas de negocio

## Una policy no es lugar para una regla de negocio

Shield registra un `Gate::before` para `super_admin` (`config/filament-shield.php`,
`define_via_gate => true`). Ese gate responde `true` **antes** de consultar la policy,
así que cualquier regla del tipo «esto no se puede borrar» o «esto no se toca si está
cerrado» escrita en la policy **no aplica justo a quien más usa el panel**.

La barrera va en el **modelo**, con eventos (`deleting` devolviendo `false`, `saving`
forzando el valor): así vale venga la orden del botón, del borrado en lote, de un
comando o de tinker. La policy y el `->hidden()` del botón siguen sirviendo, pero solo
como capa de interfaz.

Al probarlo, comprobar **el efecto** (el registro sigue ahí), no el permiso: un
`$usuario->can('delete', $registro)` en verde no prueba nada para el administrador.

## Hay un solo `Gate::before` y es el de Shield

No agregues otro `Gate::before` en `AppServiceProvider`: dos caminos para lo mismo son
imposibles de razonar y una policy futura se salta por cualquiera de los dos. Candado:
`tests/Feature/GateBeforeUnicoTest.php`.

## `shield:generate` borra los métodos propios de las policies

Escribe solo los métodos estándar y **elimina los tuyos** (`export()`, etc.), junto con
el comentario que advertía de ello. En rrhh-graneros dejó la exportación a Excel oculta
para todos, dos veces el mismo día. Después de cualquier `shield:generate`: correr la
suite y **revisar el diff de `app/Policies/` antes de commitear**.

## Un modelo sin policy queda ABIERTO, no restringido

Filament autoriza por defecto cuando no encuentra policy. Y la policy de un modelo que
vive fuera de `App\Models` (los del vendor, como `Spatie\Activitylog\Models\Activity`)
**no se descubre sola**: se registra a mano con `Gate::policy(...)` en
`AppServiceProvider`. No quites esa línea; sin ella el recurso queda abierto y
`Gate::getPolicyFor()` devuelve `null` sin avisar.
