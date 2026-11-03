<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Mon profil : identité, photo (ajout / changement / suppression), mot de passe et activité récente. */
class ParametresController extends Controller
{
    public function index(Request $request)
    {
        return view('parametres.index', [
            'user' => $request->user()->load('role'),
            'activite' => ActivityLog::where('user_id', $request->user()->id)->latest('id')->take(8)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'telephone' => ['nullable', 'string', 'max:40'],
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], ['current_password.required_with' => 'Saisissez votre mot de passe actuel pour en définir un nouveau.', 'current_password.current_password' => 'Le mot de passe actuel est incorrect.']);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->telephone = $data['telephone'] ?? null;
        $motDePasse = ! empty($data['password']);
        if ($motDePasse) {
            $user->password = Hash::make($data['password']);
        }
        $changes = array_keys($user->getDirty());
        $user->save();
        AuditLogger::log('profil.modifie', $user, ['champs' => array_values(array_diff($changes, ['password'])), 'mot_de_passe_change' => $motDePasse]);

        return back()->with('success', 'Profil mis à jour.');
    }

    public function avatar(Request $request)
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']], ['avatar.max' => 'La photo ne doit pas dépasser 2 Mo.']);
        $user = $request->user();

        if ($user->avatar_chemin) {
            Storage::delete($user->avatar_chemin);
        }
        $user->update(['avatar_chemin' => $request->file('avatar')->store('avatars')]);
        AuditLogger::log('profil.photo_modifiee', $user);

        return back()->with('success', 'Photo de profil enregistrée.');
    }

    public function supprimerAvatar(Request $request)
    {
        $user = $request->user();
        if ($user->avatar_chemin) {
            Storage::delete($user->avatar_chemin);
            $user->update(['avatar_chemin' => null]);
            AuditLogger::log('profil.photo_supprimee', $user);
        }

        return back()->with('success', 'Photo de profil supprimée.');
    }

    /** Sert la photo d'un utilisateur connecté (stockage privé, jamais exposé publiquement). */
    public function afficherAvatar(User $user)
    {
        abort_unless($user->avatar_chemin && Storage::exists($user->avatar_chemin), 404);

        return Storage::response($user->avatar_chemin, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
