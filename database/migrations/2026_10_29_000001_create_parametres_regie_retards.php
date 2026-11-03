<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paramètres métier (tarifs, taux, en-tête officielle) : une ligne par clé, valeur JSON.
        Schema::create('parametres', function (Blueprint $table) {
            $table->string('cle')->primary();
            $table->json('valeur');
            $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Circuit Régisseuse -> Trésor -> RAF/Comptable (ordre de versement).
        Schema::create('dossiers_regie', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('date_journal')->unique(); // une seule quittance par jour
            $table->decimal('tunnel', 14, 2)->default(0);
            $table->decimal('froid', 14, 2)->default(0);
            $table->decimal('transport', 14, 2)->default(0);
            $table->decimal('montant', 14, 2);
            $table->enum('statut', ['quittance_emise', 'verse', 'recu_obtenu', 'transmis_raf', 'cloture'])->default('quittance_emise')->index();
            $table->timestamp('verse_le')->nullable();
            $table->string('recu_tresor_num', 60)->nullable();
            $table->timestamp('recu_tresor_le')->nullable();
            $table->timestamp('transmis_raf_le')->nullable();
            $table->string('ordre_versement_num', 60)->nullable();
            $table->timestamp('ordre_versement_le')->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->foreignId('cloture_par')->nullable()->constrained('users');
            $table->timestamps();
        });

        // Dépassements des 24 h en tunnel et responsabilité (client = majoré, usine = non majoré).
        Schema::create('retards_tunnel', function (Blueprint $table) {
            $table->id();
            $table->string('lot', 40);
            $table->string('client', 150);
            $table->decimal('poids_kg', 12, 2);
            $table->unsignedInteger('heures_tunnel');
            $table->enum('responsabilite', ['client', 'usine']);
            $table->decimal('taux_applique', 6, 2); // taux de majoration en vigueur au moment du calcul
            $table->decimal('montant_tunnel', 14, 2);
            $table->decimal('penalite', 14, 2)->default(0);
            $table->foreignId('saisi_par')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retards_tunnel');
        Schema::dropIfExists('dossiers_regie');
        Schema::dropIfExists('parametres');
    }
};
