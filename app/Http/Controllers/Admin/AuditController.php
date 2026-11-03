<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Fmt;
use Illuminate\Http\Request;

/** Journal d'activité : consultation, filtres et export. Le RH est limité aux modules RH. */
class AuditController extends Controller
{
    /** Modules visibles avec la seule permission « activite.voir ». */
    private const MODULES_RH = ['employe', 'contrat', 'absence', 'entite_rh', 'reference', 'lettre_rh', 'corbeille', 'profil'];

    private function complet(): bool
    {
        return auth()->user()->can('admin.audit');
    }

    private function requete(Request $request)
    {
        return ActivityLog::with('user')
            ->when(! $this->complet(), fn ($q) => $q->where(fn ($w) => collect(self::MODULES_RH)->each(fn ($m) => $w->orWhere('action', 'like', $m . '.%'))))
            ->when($request->user_id, fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->module, fn ($q, $m) => $q->where('action', 'like', $m . '.%'))
            ->when($request->du, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->au, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('action', 'like', "%{$t}%")->orWhere('details', 'like', "%{$t}%")->orWhere('ip', 'like', "%{$t}%")))
            ->orderByDesc('id');
    }

    public function index(Request $request)
    {
        $modules = ActivityLog::query()->distinct()->pluck('action')->map(fn ($a) => explode('.', $a)[0])->unique()->sort()->values();
        if (! $this->complet()) {
            $modules = $modules->intersect(self::MODULES_RH)->values();
        }

        return view('admin.audit', [
            'logs' => $this->requete($request)->paginate(25)->withQueryString(),
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
            'modules' => $modules,
            'total' => $this->requete(new Request)->count(),
            'restreint' => ! $this->complet(),
        ]);
    }

    public function export(Request $request)
    {
        $logs = $this->requete($request)->limit(5000)->get();

        return response()->streamDownload(function () use ($logs) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, ['Date', 'Utilisateur', 'Action', 'Détails', 'IP'], ';');
            foreach ($logs as $l) {
                fputcsv($o, array_map([Fmt::class, 'csv'], [$l->created_at->format('d/m/Y H:i:s'), $l->user?->name ?? '—', $l->action, json_encode($l->details, JSON_UNESCAPED_UNICODE), $l->ip]), ';');
            }
            fclose($o);
        }, 'journal_activite_' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
