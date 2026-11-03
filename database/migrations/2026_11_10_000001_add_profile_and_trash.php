<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables ajoutées à la corbeille (suppression logique). `recettes` l'a déjà. */
    private const CORBEILLE = ['employes', 'clients', 'ordres_depense', 'contrats'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telephone', 40)->nullable()->after('email');
            $table->string('avatar_chemin')->nullable()->after('telephone');
        });

        foreach (self::CORBEILLE as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->softDeletes());
        }

        // Le journal sert de journal d'activité : on filtre par utilisateur / date / module.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', fn (Blueprint $t) => $t->dropIndex(['user_id', 'created_at']));
        foreach (self::CORBEILLE as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropSoftDeletes());
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['telephone', 'avatar_chemin']));
    }
};
