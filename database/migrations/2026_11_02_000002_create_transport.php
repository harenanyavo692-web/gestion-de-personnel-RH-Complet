<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 120)->unique();
            $table->string('immatriculation', 30)->nullable();
            $table->enum('type', ['poids_lourd', 'vehicule_leger', 'maritime'])->default('poids_lourd');
            $table->enum('statut', ['disponible', 'en_transit', 'maintenance'])->default('disponible');
            $table->timestamps();
        });

        Schema::create('missions_transport', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('vehicule_id')->constrained('vehicules');
            $table->foreignId('lot_id')->nullable()->constrained('lots')->nullOnDelete();
            $table->enum('prestation', ['interurbain', 'urbain', 'location', 'exception']);
            $table->string('destination', 150)->nullable();   // liaison interurbaine
            $table->string('zone_urbaine', 20)->nullable();
            $table->enum('option_carburant', ['avecGO', 'sansGO'])->nullable();
            $table->string('lieu_depart', 150)->nullable();
            $table->string('lieu_destination', 150)->nullable();
            $table->string('espece', 80)->nullable();
            $table->decimal('poids_kg', 12, 2);
            $table->string('chauffeur', 120);
            $table->string('chauffeur_tel', 40)->nullable();
            $table->string('chauffeur_cin', 40)->nullable();
            $table->string('destinataire_nom', 150)->nullable();
            $table->string('destinataire_tel', 40)->nullable();
            $table->string('agrement', 80)->nullable();
            $table->decimal('cout_standard', 14, 2);
            $table->decimal('cout', 14, 2);           // éventuellement négocié
            $table->boolean('negocie')->default(false);
            $table->enum('statut', ['attente_caisse', 'valide', 'annule'])->default('attente_caisse')->index();
            $table->foreignId('recette_id')->nullable()->constrained('recettes')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions_transport');
        Schema::dropIfExists('vehicules');
    }
};
