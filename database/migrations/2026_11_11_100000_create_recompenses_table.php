<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recompenses', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('points');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'points']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recompenses');
    }
};
