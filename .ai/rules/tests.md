---
paths:
  - 'tests/**'
  - 'e2e/**'
---

# Verde falso: lo que la suite NO prueba

## `Livewire::test()` deshabilita TODO el middleware

Hace un POST real a `/livewire/update`, pero lo envuelve en
`temporarilyDisableExceptionHandlingAndMiddleware()`
(`vendor/livewire/livewire/src/Features/SupportTesting/RequestBroker.php`), que activa
`shouldSkipMiddleware()`. **Ningún middleware propio se ejecuta.** Una prueba que use
ese helper para comprobar que un middleware hace algo pasa en verde sin haberlo
ejercitado nunca.

Para probar de verdad la convivencia middleware ↔ componente: colgar el middleware de
la ruta real `livewire/update` y despachar con `$this->postJson()`.

## Los tests corren un solo ciclo de vida: no reproducen Octane

Todo lo que dependa de que el proceso muera entre peticiones (bindings `scoped` que
Octane descarta, singletons que sobreviven, `register_shutdown_function`) pasa en verde
en la suite y falla en el navegador. Para simularlo: `app()->forgetScopedInstances()`
antes de resolver el servicio. Para confirmarlo: navegador real, o una ruta temporal que
haga `dump` dentro de una petición HTTP.

## Prueba el efecto, no el permiso

Con el `Gate::before` de `super_admin`, `$usuario->can(...)` en verde no dice nada sobre
la regla de negocio. Afirma que el registro sigue ahí. Ver `.ai/rules/policies.md`.

## Datos sembrados que no delatan el defecto

El desfase horario de los 18.400 marcajes de control-acceso se detectó por el **patrón
real** (picos a las 8 y las 17 h). Con datos sembrados alrededor del mediodía habría
pasado por bueno. Cuando pruebes algo temporal, siembra en los bordes del día.

Y no dependas del calendario: hubo CI que fallaba los primeros días del mes. Congela la
hora con `travelTo()` en vez de calcular contra `now()`.
