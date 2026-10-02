---
paths:
  - 'composer.json'
---

# Dependencias: el caret en 0.x no cruza el minor

`^0.17` significa `>=0.17 <0.18` y `^0.1` significa `>=0.1 <0.2`. En las versiones 0.x
el caret **no cruza el minor**, así que publicar 0.18.0 de un paquete propio no llega a
ningún consumidor con `composer update`: no falla, simplemente no actualiza. Eso da la
falsa sensación de que un arreglo de seguridad ya se propagó porque «el composer update
pasó sin error».

Casos vivos del ecosistema (verificado 2026-09-05):

- `muni-graneros/laravel-muni-ui` en `^0.17` en los 8 consumidores.
- `muni-graneros/laravel-muni-candados` en `^0.1` (require-dev) en 8 sistemas + este
  scaffold: el arreglo de `GuardaDeCredencialesDePlantilla` (0.2.x) no lo recibía nadie.

**En cada salto minor de un paquete 0.x hay que editar a mano el constraint del
`composer.json` de cada consumidor antes del `composer update`.** Cuando un paquete se
estabiliza, llevarlo a 1.x elimina el problema.

Al publicar un paquete propio: no tagues con el `CHANGELOG` sin publicar, o el `vendor/`
de quien lo instale dirá «Sin publicar».
