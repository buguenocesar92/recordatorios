<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * El candado contra la escalada a super_admin, en la capa de modelo.
 *
 * «UserResource» ya lo impide en SU formulario, comparando la etiqueta
 * deshabilitada de un <select>: protege ese formulario y ningún otro. Una
 * acción masiva, un importador, otro Resource o un comando artisan que
 * reparta roles sin pasar por ahí no ve ningún obstáculo.
 *
 * Se aplica una sola vez, donde de verdad convive TODO camino de escritura:
 * los eventos del pivote «AsignacionDeRol» (ver ese archivo), porque
 * «attach()», «detach()» y «sync()» de Eloquent —usados tanto por
 * «assignRole()»/«syncRoles()» del propio paquete como por
 * «saveStateToRelationship()» de Filament— terminan siempre en
 * «save()»/«delete()» sobre una instancia de ese pivote cuando la relación
 * usa una clase propia.
 */
class GuardaDeRolAdministrador
{
    /**
     * Nadie reparte el rol que lo puede todo si no lo tiene ya.
     *
     * Se aplica solo cuando hay una cuenta autenticada detrás —`auth()->user()`
     * devuelve una instancia de «User»—. No es solo «la consola»: cualquier
     * contexto SIN esa sesión resuelta queda fuera del candado, y consola es
     * apenas el más común —un comando artisan o un seeder, el camino con el
     * que un proyecto nuevo nace con su primera cuenta de administración, y
     * bloquearlo ahí impediría desplegar cualquier sistema nacido de este
     * scaffold—. Un job en cola o una API autenticada por token que no dejan
     * al guard por defecto con un usuario resuelto pasan por el mismo hueco.
     */
    public static function antesDeAsignar(int $usuarioId, int $rolId): void
    {
        if (! self::esElRolQueLoPuedeTodo($rolId)) {
            return;
        }

        $quienActua = auth()->user();

        if (! $quienActua instanceof User) {
            return;
        }

        if ($quienActua->hasRole(static::rolQueLoPuedeTodo())) {
            return;
        }

        throw ValidationException::withMessages([
            'roles' => 'Ese rol solo lo puede asignar quien ya lo tiene. Pídeselo a un administrador.',
        ]);
    }

    /**
     * Nadie deja al sistema sin un solo super_admin.
     *
     * A diferencia de «antesDeAsignar()», esto NO depende de quién actúa: es
     * un invariante del sistema, no una barrera contra un atacante concreto.
     * Si de verdad hace falta reemplazar al único super_admin, el orden
     * correcto —asignarlo primero a la cuenta nueva y quitarlo después— nunca
     * choca con este candado.
     */
    public static function antesDeQuitar(int $usuarioId, int $rolId): void
    {
        if (! self::esElRolQueLoPuedeTodo($rolId)) {
            return;
        }

        $quedanOtros = DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->where(static::columnaDelRol(), $rolId)
            ->where('model_type', (new User)->getMorphClass())
            ->where(static::columnaDelModelo(), '!=', $usuarioId)
            ->exists();

        if ($quedanOtros) {
            return;
        }

        throw ValidationException::withMessages([
            'roles' => 'No puedes quitarle este rol: es la única cuenta que lo tiene y el panel se quedaría sin nadie que pueda administrarlo todo.',
        ]);
    }

    public static function rolQueLoPuedeTodo(): string
    {
        return (string) config('filament-shield.super_admin.name', 'super_admin');
    }

    public static function columnaDelRol(): string
    {
        return (string) (config('permission.column_names.role_pivot_key') ?? 'role_id');
    }

    public static function columnaDelModelo(): string
    {
        return (string) config('permission.column_names.model_morph_key', 'model_id');
    }

    private static function esElRolQueLoPuedeTodo(int $rolId): bool
    {
        return Role::find($rolId)?->name === static::rolQueLoPuedeTodo();
    }
}
