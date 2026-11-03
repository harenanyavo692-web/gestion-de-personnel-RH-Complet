<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['employes', 'stagiaires', 'contrats', 'clients', 'recettes', 'ordres_depense', 'lettres_rh'];

    public function up(): void
    {
        foreach (self::TABLES as $nom) {
            Schema::table($nom, function (Blueprint $table) use ($nom) {
                $table->timestamp('archived_at')->nullable()->index();
                $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                // Les stagiaires et les lettres passent eux aussi par la corbeille avant toute suppression définitive.
                if (in_array($nom, ['stagiaires', 'lettres_rh'], true)) {
                    $table->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $nom) {
            Schema::table($nom, function (Blueprint $table) use ($nom) {
                $table->dropConstrainedForeignId('archived_by');
                $table->dropColumn('archived_at');
                if (in_array($nom, ['stagiaires', 'lettres_rh'], true)) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
