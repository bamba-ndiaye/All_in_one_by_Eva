<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_fidelite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients');
            $table->string('type', 20);
            $table->integer('points');                               // signé : + gain, - utilisation / retrait
            $table->foreignId('paiement_id')->nullable()->constrained('paiements');
            $table->foreignId('recompense_id')->nullable()->constrained('recompenses');
            $table->string('motif', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['client_id', 'id']);
            // Un paiement ne rapporte des points qu'une fois, et ne les retire qu'une fois.
            $table->unique(['paiement_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_fidelite');
    }
};
