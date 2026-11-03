<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Détails de bon (entrée/sortie/mission) portés par la recette ; montant = encaissé, montant_facture = facturé.
        Schema::table('recettes', function (Blueprint $table) {
            $table->string('numero_bon', 40)->nullable()->after('reference');
            $table->string('client', 150)->nullable()->after('origine');
            $table->string('espece', 80)->nullable()->after('client');
            $table->decimal('poids_kg', 12, 2)->nullable()->after('espece');
            $table->decimal('montant_facture', 14, 2)->nullable()->after('montant');
            $table->string('destination', 150)->nullable()->after('poids_kg');
        });

        Schema::create('comptes_speciaux', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('client', 150);
            $table->string('motif', 200);
            $table->decimal('montant_convenu', 14, 2);
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamps();
        });

        Schema::create('versements_speciaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compte_special_id')->constrained('comptes_speciaux')->cascadeOnDelete();
            $table->foreignId('recette_id')->constrained('recettes');
            $table->decimal('montant', 14, 2);
            $table->date('date_versement');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versements_speciaux');
        Schema::dropIfExists('comptes_speciaux');
        Schema::table('recettes', function (Blueprint $table) {
            $table->dropColumn(['numero_bon', 'client', 'espece', 'poids_kg', 'montant_facture', 'destination']);
        });
    }
};
