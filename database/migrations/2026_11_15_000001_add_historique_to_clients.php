<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('volume_historique', 14, 2)->default(0);     // kg brassés avant l'ERP (estimation)
            $table->string('tendance', 10)->default('stable');           // tendance initiale : hausse | stable | baisse
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['volume_historique', 'tendance']);
        });
    }
};
