<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UtilisateurController extends Controller
{
    public function index()
    {
        return view('admin.utilisateurs.index', [
            'utilisateurs' => User::with('role')->orderBy('name')->paginate(15),
            'roles' => Role::with('permissions')->orderBy('libelle')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.utilisateurs.form', ['utilisateur' => new User(['actif' => true]), 'roles' => Role::orderBy('libelle')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([...$data, 'actif' => true]);
        AuditLogger::log('utilisateur.cree', $user, ['email' => $user->email, 'role_id' => $user->role_id]);

        return redirect()->route('admin.utilisateurs.index')->with('success', 'Utilisateur créé.');
    }

    public function edit(User $utilisateur)
    {
        return view('admin.utilisateurs.form', ['utilisateur' => $utilisateur, 'roles' => Role::orderBy('libelle')->get()]);
    }

    public function update(Request $request, User $utilisateur)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($utilisateur)],
            'role_id' => ['required', 'exists:roles,id'],
            'actif' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);
        $data['actif'] = $request->boolean('actif');

        // Un administrateur ne peut pas se retirer ses propres accès.
        if ($utilisateur->is($request->user())) {
            $data['actif'] = true;
            $data['role_id'] = $utilisateur->role_id;
        }
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $avant = ['role_id' => $utilisateur->role_id, 'actif' => $utilisateur->actif];
        $utilisateur->update($data);
        AuditLogger::log('utilisateur.modifie', $utilisateur, [
            'avant' => $avant,
            'apres' => ['role_id' => $utilisateur->role_id, 'actif' => $utilisateur->actif],
            'mot_de_passe_change' => isset($data['password']),
        ]);

        return redirect()->route('admin.utilisateurs.index')->with('success', 'Utilisateur mis à jour.');
    }
}
