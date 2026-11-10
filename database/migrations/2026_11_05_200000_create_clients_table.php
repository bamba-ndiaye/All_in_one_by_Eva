<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('prenom', 100);
            $table->string('nom', 100);
            $table->string('telephone', 20)->unique();      // format international : +221771234567
            $table->string('whatsapp', 20)->nullable();     // seulement s'il diffère du téléphone
            $table->string('email', 150)->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('canal_prefere', 20)->nullable();
            $table->boolean('consentement_promo')->default(false);
            $table->timestamp('date_consentement')->nullable();
            $table->timestamp('date_retrait_consentement')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['nom', 'prenom']);
            $table->index('is_active');
            $table->index('date_naissance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};