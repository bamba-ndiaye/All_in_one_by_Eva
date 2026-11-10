<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employe_service', function (Blueprint $table) {
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();

            $table->primary(['employe_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employe_service');
    }
};