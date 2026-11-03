<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stagiaires', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('etablissement');
            $table->string('filiere')->nullable();
            $table->foreignId('entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->foreignId('tuteur_id')->nullable()->constrained('employes')->nullOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('gratification', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stagiaires');
    }
};
