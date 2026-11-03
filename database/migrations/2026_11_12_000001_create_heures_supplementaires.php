<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heures_supplementaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->restrictOnDelete();
            $table->date('date');
            $table->decimal('heures', 5, 2);
            $table->unsignedSmallInteger('majoration')->default(30); // % : 30 (8 premières h/sem.), 50 au-delà
            $table->string('motif')->nullable();
            $table->decimal('montant', 14, 2)->nullable(); // figé au paiement
            $table->date('date_paiement')->nullable();
            $table->foreignId('paye_par')->nullable()->constrained('users');
            $table->foreignId('saisi_par')->constrained('users');
            $table->timestamps();
            $table->index(['employe_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heures_supplementaires');
    }
};
