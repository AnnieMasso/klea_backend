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
        Schema::create('biens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bailleur_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('titre');
            $table->string('type');
            $table->text('description');
            $table->integer('montant');
            $table->string('modalité_paiement');
            $table->enum('statut', ['actif', 'inactif'])->default('inactif');
            $table->integer('superficie');
            $table->integer('nb_chambres');
            $table->integer('nb_douches');
            $table->boolean('ammeublement');
            $table->string('ville');
            $table->string('quartier');
            $table->string('lieu_dit');
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biens');
    }
};
