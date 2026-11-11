<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendez_vous_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rendez_vous_id')->constrained('rendez_vous')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('forfait_id')->nullable()->constrained('forfaits');
            $table->string('nom', 150);
            $table->unsignedInteger('prix_applique');
            $table->unsignedSmallInteger('duree_appliquee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendez_vous_service');
    }
};