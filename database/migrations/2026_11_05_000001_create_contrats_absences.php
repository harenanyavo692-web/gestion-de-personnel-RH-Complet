<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->restrictOnDelete();
            $table->string('type_code', 40);               // reference_items.groupe = type_contrat
            $table->date('date_debut');
            $table->date('date_fin')->nullable();          // vide = durée indéterminée
            $table->decimal('salaire', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->enum('statut', ['actif', 'termine'])->default('actif')->index();
            $table->date('termine_le')->nullable();
            $table->string('motif_fin', 200)->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamps();
        });

        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->restrictOnDelete();
            $table->string('type_code', 40);               // reference_items.groupe = type_absence
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('jours', 5, 1);                // jours ouvrés (lundi-vendredi)
            $table->string('motif', 300)->nullable();
            $table->string('justificatif_chemin')->nullable();
            $table->string('justificatif_nom')->nullable();
            $table->enum('statut', ['en_attente', 'validee', 'refusee', 'annulee'])->default('en_attente')->index();
            $table->foreignId('demande_par')->constrained('users');
            $table->foreignId('traite_par')->nullable()->constrained('users');
            $table->timestamp('traite_le')->nullable();
            $table->string('commentaire', 300)->nullable();
            $table->timestamps();
            $table->index(['employe_id', 'date_debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absences');
        Schema::dropIfExists('contrats');
    }
};
