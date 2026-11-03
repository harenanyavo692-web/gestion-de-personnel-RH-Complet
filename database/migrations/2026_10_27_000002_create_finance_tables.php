<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_financieres', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->enum('type', ['recette', 'depense']);
            $table->timestamps();
            $table->unique(['nom', 'type']);
        });

        Schema::create('recettes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('date_recette')->index();
            $table->decimal('montant', 14, 2);
            $table->foreignId('categorie_id')->constrained('categories_financieres');
            $table->string('origine');
            $table->text('note')->nullable();
            $table->foreignId('saisi_par')->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('ordres_depense', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('date_depense')->index();
            $table->string('motif');
            $table->string('beneficiaire')->nullable();
            $table->decimal('montant', 14, 2);
            $table->foreignId('categorie_id')->constrained('categories_financieres');
            $table->enum('statut', ['en_attente', 'validee', 'refusee'])->default('en_attente')->index();
            $table->foreignId('soumis_par')->constrained('users');
            $table->foreignId('traite_par')->nullable()->constrained('users');
            $table->timestamp('traite_le')->nullable();
            $table->text('commentaire_raf')->nullable();
            $table->timestamps();
        });

        // Justificatif rattaché à une recette ou à un ordre de dépense.
        Schema::create('pieces_justificatives', function (Blueprint $table) {
            $table->id();
            $table->morphs('piece');
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('taille')->default(0);
            $table->foreignId('ajoute_par')->constrained('users');
            $table->timestamps();
        });

        Schema::create('paiements_salaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->restrictOnDelete();
            $table->char('mois', 7); // AAAA-MM
            $table->decimal('montant', 14, 2);
            $table->date('date_paiement');
            $table->foreignId('paye_par')->constrained('users');
            $table->timestamps();
            $table->unique(['employe_id', 'mois']);
        });

        Schema::create('rapprochements', function (Blueprint $table) {
            $table->id();
            $table->string('periode');
            $table->date('date_arrete');
            $table->decimal('solde_livres', 14, 2);
            $table->decimal('solde_tresor', 14, 2);
            $table->decimal('ecart_final', 14, 2);
            $table->enum('statut', ['rapproche', 'ecart'])->index();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamps();
        });

        Schema::create('rapprochement_ecarts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapprochement_id')->constrained()->cascadeOnDelete();
            $table->string('libelle');
            $table->decimal('montant', 14, 2); // signé
        });

        Schema::create('commentaires_rapport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->text('contenu');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('cible_type')->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['cible_type', 'cible_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'commentaires_rapport', 'rapprochement_ecarts', 'rapprochements',
                  'paiements_salaires', 'pieces_justificatives', 'ordres_depense', 'recettes',
                  'categories_financieres'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
