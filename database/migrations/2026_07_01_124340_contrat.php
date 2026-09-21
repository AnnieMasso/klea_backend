<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('type');
            $table->enum('etat', ['en attente', 'signe', 'annule', 'resilie'])->default('en attente');
            $table->date('date_resiliation')->nullable();
            $table->date('date_signature')->nullable();
            $table->date('date_annulation')->nullable();
            $table->integer('montant_loyer');
            $table->integer('duree');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contrats');
    }
};
