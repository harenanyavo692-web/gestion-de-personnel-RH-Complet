<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table "employes" — fiche personnel du CDPHM
     */
    public function up(): void
    {
        Schema::create('employes', function (Blueprint $table) {
            $table->id();
            $table->string('matricule')->unique();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->unique();
            $table->string('telephone')->nullable();
            $table->string('poste');
            $table->enum('departement', [
                'Direction',
                'Chambre froide',
                'Réception & pesée',
                'Distribution',
                'Comptabilité',
                'Ressources Humaines',
                'Maintenance',
                'Sécurité',
            ]);
            $table->date('date_embauche');
            $table->decimal('salaire', 12, 2)->nullable();
            $table->enum('statut', ['actif', 'congé', 'suspendu', 'inactif'])->default('actif');
            $table->string('photo')->nullable();
            $table->text('adresse')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employes');
    }
};
