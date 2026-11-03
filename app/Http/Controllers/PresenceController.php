<?php

namespace App\Http\Controllers;

use App\Models\Employe;
use App\Models\Presence;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->get('date', now()->toDateString());

        $employes = Employe::where('statut', '!=', 'inactif')
            ->orderBy('nom')
            ->with(['presences' => fn ($q) => $q->whereDate('date', $date)])
            ->get();

        $types = $employes->map(fn ($e) => $e->presences->first()?->type);
        $resume = [
            'presence' => $types->filter(fn ($t) => $t === 'présence')->count(),
            'absence' => $types->filter(fn ($t) => $t === 'absence')->count(),
            'conge' => $types->filter(fn ($t) => $t === 'congé')->count(),
            'mission' => $types->filter(fn ($t) => $t === 'mission')->count(),
            'non_pointe' => $types->filter(fn ($t) => $t === null)->count(),
        ];

        return view('presences.index', compact('employes', 'date', 'resume'));
    }

    public function pointer(Request $request, Employe $employe)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'type' => 'required|in:présence,absence',
        ]);

        $existant = Presence::where('employe_id', $employe->id)->whereDate('date', $data['date'])->first();
        if ($existant && str_starts_with((string) $existant->note, 'absence #')) {
            return back()->withErrors(['type' => "Ce jour provient d'une demande de congé, d'absence ou de mission validée : annulez la demande pour le modifier."]);
        }

        Presence::updateOrCreate(
            ['employe_id' => $employe->id, 'date' => $data['date']],
            [
                'type' => $data['type'],
                'heure_arrivee' => $data['type'] === 'présence' ? now()->format('H:i') : null,
            ]
        );

        return back()->with('success', "Pointage enregistré pour {$employe->prenom} {$employe->nom}.");
    }
}
