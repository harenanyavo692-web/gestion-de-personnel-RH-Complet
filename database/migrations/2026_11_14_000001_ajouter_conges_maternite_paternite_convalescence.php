<?php

use App\Models\ReferenceItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const TYPES = [
        'PATERNITE' => 'Congé de paternité',
        'CONVALESCENCE' => 'Congé de convalescence',
    ];

    public function up(): void
    {
        // « Maternité / paternité » est scindé : la maternité garde son code, la paternité devient un type à part.
        ReferenceItem::where('groupe', 'type_absence')->where('code', 'MATERNITE')->update(['libelle' => 'Congé de maternité']);

        $ordre = (int) ReferenceItem::where('groupe', 'type_absence')->max('ordre');
        foreach (self::TYPES as $code => $libelle) {
            ReferenceItem::firstOrCreate(['groupe' => 'type_absence', 'code' => $code], [
                'domaine' => 'rh', 'libelle' => $libelle, 'ordre' => ++$ordre,
                'meta' => ['jours_annuels' => null, 'justificatif' => true, 'nature' => 'conge'],
            ]);
        }
    }

    public function down(): void
    {
        ReferenceItem::where('groupe', 'type_absence')->whereIn('code', array_keys(self::TYPES))->delete();
        ReferenceItem::where('groupe', 'type_absence')->where('code', 'MATERNITE')->update(['libelle' => 'Maternité / paternité']);
    }
};
