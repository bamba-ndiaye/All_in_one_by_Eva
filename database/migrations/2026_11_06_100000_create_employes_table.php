<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('telephone', 30)->nullable();
            $table->string('fonction', 100);
            $table->text('specialites')->nullable();
            $table->string('commission_type', 20)->nullable();
            $table->unsignedInteger('commission_valeur')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['nom', 'prenom']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employes');
    }
};