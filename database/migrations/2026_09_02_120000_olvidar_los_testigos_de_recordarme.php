<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Olvida de una vez los testigos de «Recordarme» repartidos hasta hoy.
 *
 * El sistema ya descarta esa cookie antes de que el guard la vea
 * («IgnorarCookieDeRecordar») y nadie vuelve a emitirla, pero las que se
 * entregaron antes siguen guardadas en los navegadores y su testigo sigue
 * siendo válido en esta tabla. Mientras lo sea, la cookie vale para cualquier
 * camino que se le escape al middleware: una ruta nueva registrada fuera del
 * grupo «web» y de la lista del panel, un panel que se agregue mañana. Ya se
 * escapó una vez —el rechazo vivía solo en el panel—, así que la puerta se
 * cierra también del lado de la base y no solo del lado del código.
 *
 * Vaciar la columna no cierra ninguna sesión abierta: la sesión vive en su
 * propio almacén. Lo único que deja de funcionar es entrar sin contraseña.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'remember_token')) {
            return;
        }

        DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);
    }

    /**
     * No hay vuelta atrás: los testigos viejos no se guardaron en ningún lado
     * antes de borrarlos, que es justamente el punto.
     */
    public function down(): void {}
};
