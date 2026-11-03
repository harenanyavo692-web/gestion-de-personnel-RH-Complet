<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Matrice rôles × permissions. Le rôle administrateur est verrouillé pour éviter tout blocage d'accès. */
class RoleController extends Controller
{
    private const VERROUILLES = ['admin'];

    public function index()
    {
        $permissions = Permission::orderBy('slug')->get()->groupBy(fn ($p) => explode('.', $p->slug)[0]);

        return view('admin.roles', [
            'roles' => Role::with('permissions:id,slug')->withCount('users')->orderBy('libelle')->get(),
            'groupes' => $permissions, 'verrouilles' => self::VERROUILLES,
        ]);
    }

    /** Crée un rôle, éventuellement en copiant les permissions d'un rôle existant. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:80'],
            'copie_de' => ['nullable', 'exists:roles,id'],
        ]);
        $slug = Str::slug($data['libelle'], '_');
        if ($slug === '' || Role::where('slug', $slug)->exists()) {
            return back()->withErrors(['libelle' => 'Un rôle portant ce nom existe déjà.'])->withInput();
        }

        $role = Role::create(['slug' => $slug, 'libelle' => $data['libelle']]);
        if (! empty($data['copie_de'])) {
            $source = Role::findOrFail($data['copie_de']);
            // Les droits d'administration ne se copient pas depuis le rôle verrouillé.
            $role->permissions()->sync($source->slug === 'admin' ? [] : $source->permissions()->pluck('permissions.id'));
        }
        AuditLogger::log('role.cree', $role, ['role' => $role->slug, 'copie_de' => $data['copie_de'] ?? null]);

        return back()->with('success', "Rôle « {$role->libelle} » créé : cochez ses permissions puis enregistrez.");
    }

    public function update(Request $request, Role $role)
    {
        abort_if(in_array($role->slug, self::VERROUILLES, true), 403, 'Ce rôle est verrouillé.');
        $data = $request->validate(['permissions' => ['nullable', 'array'], 'permissions.*' => ['integer', 'exists:permissions,id']]);

        $avant = $role->permissions()->pluck('slug')->all();
        $role->permissions()->sync($data['permissions'] ?? []);
        $apres = $role->permissions()->pluck('slug')->all();

        AuditLogger::log('role.permissions_modifiees', $role, [
            'role' => $role->slug, 'ajoutees' => array_values(array_diff($apres, $avant)), 'retirees' => array_values(array_diff($avant, $apres)),
        ]);

        return back()->with('success', "Permissions du rôle « {$role->libelle} » enregistrées.");
    }

    public function destroy(Role $role)
    {
        abort_if(in_array($role->slug, self::VERROUILLES, true), 403, 'Ce rôle est verrouillé.');
        if ($role->users()->exists()) {
            return back()->withErrors(['role' => "Suppression impossible : des utilisateurs ont le rôle « {$role->libelle} ». Réaffectez-les d'abord."]);
        }
        AuditLogger::log('role.supprime', $role, ['role' => $role->slug]);
        $role->delete();

        return back()->with('success', 'Rôle supprimé.');
    }
}
