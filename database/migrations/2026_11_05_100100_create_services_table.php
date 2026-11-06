<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_service_id')->constrained('categories_services');
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('prix');                  // FCFA, entier
            $table->unsignedSmallInteger('duree_minutes');
            $table->string('zone', 20)->default('mixte');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['categorie_service_id', 'nom']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};