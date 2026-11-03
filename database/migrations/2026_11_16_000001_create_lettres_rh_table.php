<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lettres_rh', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('type', 30)->index();
            $table->foreignId('employe_id')->nullable()->constrained('employes')->nullOnDelete();
            $table->string('nom', 150);
            $table->string('poste', 150);
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->text('motif')->nullable();
            $table->string('signataire', 150)->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lettres_rh');
    }
};
