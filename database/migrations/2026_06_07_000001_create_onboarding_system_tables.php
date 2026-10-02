<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tours de onboarding (recorridos guiados)
        Schema::create('onboarding_tours', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('system', 50); // ej. 'omil', 'feria', 'hub', 'admin'
            $table->string('role', 100);   // rol al que aplica el tour (ej. 'administrador', 'operador')
            $table->string('surface', 50)->nullable(); // ej. 'app', 'panel', 'hub'
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Un solo tour activo/definido por combinación sistema+rol+superficie
            $table->unique(['system', 'role', 'surface'], 'tour_system_role_surface_unique');
            $table->index(['system', 'role', 'active']);
        });

        // 2. Pasos de cada tour
        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')
                ->constrained('onboarding_tours')
                ->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('title');
            $table->text('description');
            $table->string('element_selector')->nullable();
            $table->string('pre_action_selector')->nullable();
            $table->enum('position', ['top', 'bottom', 'left', 'right'])->default('bottom');
            $table->timestamps();

            $table->index(['tour_id', 'order']);
        });

        // 3. Progreso de los usuarios
        Schema::create('onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('tour_id')
                ->constrained('onboarding_tours')
                ->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tour_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_progress');
        Schema::dropIfExists('onboarding_steps');
        Schema::dropIfExists('onboarding_tours');
    }
};
