<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stagiaires', function (Blueprint $table) {
            $table->string('certificat_numero', 40)->nullable()->unique();
            $table->date('certificat_le')->nullable();
            $table->foreignId('certificat_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('appreciation', 40)->nullable();   // reference : passable, bien, tres_bien, excellent
            $table->text('commentaire_certificat')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stagiaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificat_par');
            $table->dropColumn(['certificat_numero', 'certificat_le', 'appreciation', 'commentaire_certificat']);
        });
    }
};
