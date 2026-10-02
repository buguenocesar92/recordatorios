<?php

namespace App\Models\Pivots;

use App\Support\GuardaDeRolAdministrador;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * El pivote de «roles» de un usuario, con eventos de modelo.
 *
 * Existe por una sola razón: sin una clase de pivote propia, «attach()»,
 * «detach()» y «sync()» de Eloquent insertan y borran filas con una consulta
 * directa, sin disparar NINGÚN evento. Con ella —«roles()» la declara vía
 * «->using()» en «App\Models\User»— esos mismos métodos pasan por
 * «save()»/«delete()» sobre una instancia de esta clase, y ESE es el único
 * punto por el que convive todo camino que reparte o quita un rol: el
 * formulario de Filament, «assignRole()»/«syncRoles()»/«removeRole()» del
 * propio paquete, una acción masiva, un importador, un comando artisan.
 *
 * No valida nada por sí misma: delega en «GuardaDeRolAdministrador», que es
 * lo que de verdad decide y lo que tienen sus propias pruebas.
 */
class AsignacionDeRol extends MorphPivot
{
    protected static function booted(): void
    {
        static::creating(function (self $asignacion): void {
            GuardaDeRolAdministrador::antesDeAsignar(
                (int) $asignacion->getAttribute(GuardaDeRolAdministrador::columnaDelModelo()),
                (int) $asignacion->getAttribute(GuardaDeRolAdministrador::columnaDelRol()),
            );
        });

        static::deleting(function (self $asignacion): void {
            GuardaDeRolAdministrador::antesDeQuitar(
                (int) $asignacion->getAttribute(GuardaDeRolAdministrador::columnaDelModelo()),
                (int) $asignacion->getAttribute(GuardaDeRolAdministrador::columnaDelRol()),
            );
        });
    }
}
