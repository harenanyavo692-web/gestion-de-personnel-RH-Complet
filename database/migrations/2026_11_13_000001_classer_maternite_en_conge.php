<?php

use App\Models\ReferenceItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ReferenceItem::where('groupe', 'type_absence')->where('code', 'MATERNITE')->get()
            ->each(fn ($t) => $t->update(['meta' => ($t->meta ?? []) + ['nature' => 'conge']]));
    }

    public function down(): void
    {
        ReferenceItem::where('groupe', 'type_absence')->where('code', 'MATERNITE')->get()
            ->each(fn ($t) => $t->update(['meta' => collect($t->meta)->except('nature')->all()]));
    }
};
