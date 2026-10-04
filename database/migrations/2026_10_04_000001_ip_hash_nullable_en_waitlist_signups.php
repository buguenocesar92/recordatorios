<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El seudónimo de la IP solo sirve contra abusos del alta: al confirmar se
 * borra (Ley 21.719, minimización). Por eso la columna pasa a admitir NULL.
 * Compatible hacia atrás: el código anterior siempre escribe un valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_signups', function (Blueprint $table) {
            $table->char('ip_hash', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('waitlist_signups', function (Blueprint $table) {
            $table->char('ip_hash', 64)->nullable(false)->change();
        });
    }
};
