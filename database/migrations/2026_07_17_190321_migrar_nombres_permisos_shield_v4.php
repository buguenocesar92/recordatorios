<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Shield v3 nombraba los permisos de recursos multi-palabra con '::'
// (create_onboarding::tour); v4 los genera con el separador configurado
// (create_onboarding_tour). El entrypoint corre `shield:generate` en CADA arranque,
// así que sin esta migración cada deploy crearía el gemelo v4 y dejaría el
// permiso v3 huérfano: los roles conservarían el nombre viejo, que v4 ya no
// consulta, y perderían el permiso en silencio.
//
// Renombra en sitio (conserva el id, y con él las asignaciones de
// role_has_permissions) y elimina el gemelo v4 si shield ya alcanzó a crearlo.
return new class extends Migration
{
    public function up(): void
    {
        $antiguos = DB::table('permissions')->where('name', 'like', '%::%')->get();

        foreach ($antiguos as $permiso) {
            $nuevo = str_replace('::', '_', $permiso->name);

            $gemelo = DB::table('permissions')
                ->where('name', $nuevo)
                ->where('id', '!=', $permiso->id)
                ->first();

            if ($gemelo) {
                DB::table('role_has_permissions')->where('permission_id', $gemelo->id)->delete();
                DB::table('model_has_permissions')->where('permission_id', $gemelo->id)->delete();
                DB::table('permissions')->where('id', $gemelo->id)->delete();
            }

            DB::table('permissions')->where('id', $permiso->id)->update(['name' => $nuevo]);
        }
    }

    public function down(): void
    {
        // Reversa best-effort: el único recurso multi-palabra del scaffold es
        // onboarding_tour (v3 lo nombraba onboarding::tour).
        DB::table('permissions')
            ->where('name', 'like', '%onboarding_tour')
            ->update(['name' => DB::raw("REPLACE(name, 'onboarding_tour', 'onboarding::tour')")]);
    }
};
