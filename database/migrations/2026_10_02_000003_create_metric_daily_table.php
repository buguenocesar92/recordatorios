<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('metric', 64);
            $table->unsignedInteger('value')->default(0);
            $table->timestamps();
            $table->unique(['date', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_daily');
    }
};
