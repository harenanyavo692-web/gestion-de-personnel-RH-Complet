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
            $table->string('nom')->unique();
            $table->string('contact', 60)->nullable();
            $table->boolean('special')->default(false); // client ⭐ : tarif / paiement négocié
            $table->text('conditions')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('infrastructures', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->enum('type', ['tunnel', 'chambre']);
            $table->unsignedInteger('capacite_kg');
            $table->enum('statut', ['actif', 'maintenance', 'hors_service'])->default('actif');
            $table->timestamps();
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('infrastructure_id')->constrained('infrastructures');
            $table->enum('type_infrastructure', ['tunnel', 'chambre'])->index();
            $table->string('espece', 80);
            $table->unsignedInteger('nb_filets')->default(0);
            $table->unsignedInteger('nb_poissons')->default(0);
            $table->unsignedInteger('nb_bacs')->default(0);
            $table->decimal('poids_entree', 12, 2);
            $table->decimal('stock_restant', 12, 2);
            $table->string('dockers')->nullable();
            $table->dateTime('date_entree');
            $table->dateTime('date_entree_chambre')->nullable();
            $table->enum('statut', ['en_stock', 'sorti'])->default('en_stock')->index();
            $table->string('motif_entree_directe', 80)->nullable();
            $table->decimal('tarif_negocie', 10, 2)->nullable();
            $table->decimal('tarif_chambre', 10, 2)->nullable();
            $table->decimal('jours_prevus', 8, 2)->default(1);
            $table->boolean('duree_indeterminee')->default(false);
            $table->unsignedInteger('heures_tunnel')->nullable();
            $table->enum('responsabilite_depassement', ['client', 'usine'])->nullable();
            $table->decimal('montant_tunnel', 14, 2)->nullable();
            $table->decimal('penalite_tunnel', 14, 2)->default(0);
            $table->boolean('paye_tunnel')->default(false);
            $table->boolean('paye_froid')->default(false);
            $table->decimal('montant_froid_encaisse', 14, 2)->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamps();
        });

        Schema::create('sorties_partielles', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('lot_id')->constrained('lots')->cascadeOnDelete();
            $table->decimal('poids', 12, 2);
            $table->decimal('jours', 8, 2);
            $table->decimal('tarif', 10, 2);
            $table->decimal('montant', 14, 2);
            $table->foreignId('recette_id')->nullable()->constrained('recettes');
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sorties_partielles');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('infrastructures');
        Schema::dropIfExists('clients');
    }
};
