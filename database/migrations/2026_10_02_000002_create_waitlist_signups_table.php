<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_signups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Cifrado en reposo (EncryptedSeguro): un correo de 320 caracteres
            // cifrado ocupa más que eso, por eso `text`. La unicidad va en el hash.
            $table->text('email');
            $table->char('email_hash', 64)->unique();
            $table->foreignId('comuna_id')->nullable()->constrained('comunas')->nullOnDelete();
            $table->json('interests');
            $table->string('consent_version', 32);
            $table->timestamp('consented_at');
            $table->char('ip_hash', 64);
            $table->char('confirm_token_hash', 64)->nullable()->unique();
            $table->timestamp('confirm_expires_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->boolean('paid_penalty_last_year')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_signups');
    }
};
