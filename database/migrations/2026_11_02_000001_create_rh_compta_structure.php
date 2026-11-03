<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Listes de référence paramétrables (RH : catégories, statuts, grades, postes, contrats, absences ;
        // Compta : modes de paiement, types de pièces, axes analytiques).
        Schema::create('reference_items', function (Blueprint $table) {
            $table->id();
            $table->enum('domaine', ['rh', 'compta']);
            $table->string('groupe', 40);
            $table->string('code', 40);
            $table->string('libelle', 150);
            $table->json('meta')->nullable(); // ex. absence : jours par an, justificatif requis
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['groupe', 'code']);
        });

        // Structure organisationnelle (direction, département, service, unité, bureau).
        Schema::create('organization_entities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('nom', 150);
            $table->enum('type', ['direction', 'departement', 'service', 'unite', 'bureau']);
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('organization_entities')->restrictOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('employes')->nullOnDelete();
            $table->string('localisation', 150)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Le département devient un rattachement historisé : on garde le libellé (texte) et on ajoute la clé.
        Schema::table('employes', function (Blueprint $table) {
            $table->string('departement', 120)->nullable()->change();
        });
        Schema::table('employes', function (Blueprint $table) {
            $table->foreignId('entity_id')->nullable()->after('departement')->constrained('organization_entities')->nullOnDelete();
        });

        Schema::create('accounting_exercises', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('libelle', 100);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('statut', ['ouvert', 'cloture'])->default('ouvert')->index();
            $table->timestamp('cloture_le')->nullable();
            $table->foreignId('cloture_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('accounting_journals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('libelle', 100);
            $table->enum('type', ['caisse', 'banque', 'achats', 'ventes', 'operations_diverses']);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->string('libelle', 150);
            $table->unsignedTinyInteger('classe'); // 1 à 8
            $table->enum('type', ['actif', 'passif', 'charge', 'produit']);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Centres de coûts, sections budgétaires, projets… (hiérarchie via parent_id).
        Schema::create('accounting_entities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('nom', 150);
            $table->enum('type', ['centre_cout', 'section_budgetaire', 'projet', 'service_budget', 'perimetre']);
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('accounting_entities')->restrictOnDelete();
            $table->foreignId('organization_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained('accounting_exercises')->restrictOnDelete();
            $table->decimal('budget_reference', 14, 2)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('categories_financieres', function (Blueprint $table) {
            $table->boolean('actif')->default(true)->after('type');
        });

        foreach (['recettes', 'ordres_depense'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('accounting_entity_id')->nullable()->constrained('accounting_entities')->restrictOnDelete();
                $table->foreignId('journal_id')->nullable()->constrained('accounting_journals')->restrictOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('accounting_accounts')->restrictOnDelete();
                $table->string('mode_paiement', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['recettes', 'ordres_depense'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('accounting_entity_id');
                $table->dropConstrainedForeignId('journal_id');
                $table->dropConstrainedForeignId('account_id');
                $table->dropColumn('mode_paiement');
            });
        }
        Schema::table('categories_financieres', fn (Blueprint $t) => $t->dropColumn('actif'));
        Schema::dropIfExists('accounting_entities');
        Schema::dropIfExists('accounting_accounts');
        Schema::dropIfExists('accounting_journals');
        Schema::dropIfExists('accounting_exercises');
        Schema::table('employes', fn (Blueprint $t) => $t->dropConstrainedForeignId('entity_id'));
        Schema::dropIfExists('organization_entities');
        Schema::dropIfExists('reference_items');
    }
};
